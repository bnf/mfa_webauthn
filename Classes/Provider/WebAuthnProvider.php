<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Bnf\MfaWebauthn\Provider;

use Bnf\MfaWebauthn\Repository\PublicKeyCredentialSourceRepository;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Authentication\Mfa\MfaViewType;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\Server;

class WebAuthnProvider implements MfaProviderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ResponseFactoryInterface $responseFactory;
    private Context $context;
    private string $userVerification;
    private ?string $authenticatorAttachment;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        Context $context,
        string $userVerification = AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_DISCOURAGED,
        ?string $authenticatorAttachment = AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE
    ) {
        $this->responseFactory = $responseFactory;
        $this->context = $context;
        $this->userVerification = $userVerification;
        $this->authenticatorAttachment = $authenticatorAttachment;

        if (!Environment::isComposerMode()) {
            $functions = [
                'beberlei/assert/lib/Assert/functions.php',
                'ramsey/uuid/src/functions.php',
                'thecodingmachine/safe/lib/special_cases.php',
                'thecodingmachine/safe/generated/*.php',
            ];
            foreach ($functions as $glob) {
                $files = glob(__DIR__ . '/../../Resources/Private/Libraries/' . $glob);
                if ($files === false) {
                    throw new \Exception('classic-mode composer-dependencies in EXT:mfa_webauth/Resources/Private/Libraries as missing', 1679121183);
                }
                foreach ($files as $file) {
                    require_once($file);
                }
            }
        }
    }

    public function canProcess(ServerRequestInterface $request): bool
    {
        return $this->getPublicKey($request) !== '';
    }

    public function isActive(MfaProviderPropertyManager $propertyManager): bool
    {
        return (bool)$propertyManager->getProperty('active') &&
            count($propertyManager->getProperty(PublicKeyCredentialSourceRepository::PROPERTY) ?? []) > 0;
    }

    public function isLocked(MfaProviderPropertyManager $propertyManager): bool
    {
        return false;
    }

    public function handleRequest(
        ServerRequestInterface $request,
        MfaProviderPropertyManager $propertyManager,
        string $type
    ): ResponseInterface {
        $content = '';
        switch ($type) {
            case MfaViewType::SETUP:
            case MfaViewType::EDIT:
                $content = $this->prepareSetup($request, $propertyManager, $type);
                break;
            case MfaViewType::AUTH:
                $content = $this->prepareAuth($request, $propertyManager);
                break;
        }
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write($content);
        return $response;
    }

    public function unlock(ServerRequestInterface $request, MfaProviderPropertyManager $propertyManager): bool
    {
        if (!$this->isLocked($propertyManager)) {
            // Return since this provider is not locked
            return false;
        }


        // Reset the attempts
        // Note, this is just for statistics, it is not needed for webauthn, as it will not lock
        return $propertyManager->updateProperties(['attempts' => 0]);
    }

    public function deactivate(ServerRequestInterface $request, MfaProviderPropertyManager $propertyManager): bool
    {
        if (!$this->isActive($propertyManager)) {
            // Return since this provider is not activated
            return false;
        }

        // Delete the provider entry
        return $propertyManager->deleteProviderEntry();
    }

    public function activate(ServerRequestInterface $request, MfaProviderPropertyManager $propertyManager): bool
    {
        if ($this->isActive($propertyManager)) {
            return true;
        }

        return $this->update($request, $propertyManager);
    }

    public function update(ServerRequestInterface $request, MfaProviderPropertyManager $propertyManager): bool
    {
        if (!$this->canProcess($request)) {
            // Return since the request can not be processed by this provider
            return false;
        }

        $action = $this->getAction($request);
        $status = true;
        if ($action === 'add') {
            $status = $this->addCredentials($request, $propertyManager);
        }
        if ($action === 'remove') {
            $status = $this->removeCredentials($request, $propertyManager);
        }

        if (!$this->isActive($propertyManager)) {
            // Delete the provider entry (for security reasons) when we gone inactive due to the update
            if (!$propertyManager->deleteProviderEntry()) {
                $status = false;
            }
        }

        return $status;
    }

    private function addCredentials(ServerRequestInterface $request, MfaProviderPropertyManager $propertyManager): bool
    {
        $data = $this->getPublicKey($request);
        $publicKeyCredentialSourceRepository = new PublicKeyCredentialSourceRepository($propertyManager);
        $keyDescription = $this->getDescription($request);
        $keyIcon = $this->getIcon($request);

        $creationOptions = PublicKeyCredentialCreationOptions::createFromArray(
            $propertyManager->getProperty('creationOptions')
        );

        $webauthn = $this->createWebauthnServer($request, $propertyManager);

        try {
            $publicKeyCredentialSource = $webauthn->loadAndCheckAttestationResponse(
                $data,
                $creationOptions, // This one contains the challenge we stored during the previous step
                $request
            );
            $publicKeyCredentialSourceRepository->addCredentialSource($publicKeyCredentialSource, $keyDescription, $keyIcon);

        } catch (\Throwable $exception) {
            return false;
        }

        // If valid, prepare the provider properties to be stored
        $properties = [
            'active' => true,
            // reset this temporary field
            'creationOptions' => null,
        ];

        return $propertyManager->hasProviderEntry()
            ? $propertyManager->updateProperties($properties)
            : $propertyManager->createProviderEntry($properties);
    }

    private function removeCredentials(ServerRequestInterface $request, MfaProviderPropertyManager $propertyManager): bool
    {
        $data = $this->getPublicKey($request);
        $publicKeyCredentialSourceRepository = new PublicKeyCredentialSourceRepository($propertyManager);
        try {
            $credentialSource = PublicKeyCredentialSource::createFromArray(json_decode($data, true));
            $publicKeyCredentialSourceRepository->removeCredentialSource($credentialSource);
        } catch (\Throwable $e) {
            return false;
        }
        return true;
    }

    public function verify(ServerRequestInterface $request, MfaProviderPropertyManager $propertyManager): bool
    {
        $publicKey = $this->getPublicKey($request);

        $userEntity = PublicKeyCredentialUserEntity::createFromArray(
            $propertyManager->getProperty('userEntity')
        );
        // get options stored during the previous (prepareAuth) step
        $publicKeyCredentialRequestOptions = PublicKeyCredentialRequestOptions::createFromArray(
            $propertyManager->getProperty('lastRequest')
        );

        $webauthn = $this->createWebauthnServer($request, $propertyManager);

        try {
            $publicKeyCredentialSource = $webauthn->loadAndCheckAssertionResponse(
                $publicKey,
                $publicKeyCredentialRequestOptions, // The options stored during the previous (prepareAuth) step
                $userEntity,
                $request
            );
            $verified = true;
        } catch (\Throwable $e) {
            $verified = false;
        }

        if (!$verified) {
            $attempts = $propertyManager->getProperty('attempts', 0);
            $propertyManager->updateProperties(['attempts' => ++$attempts]);
            return false;
        }
        $propertyManager->updateProperties([
            'lastUsed' => $this->context->getPropertyFromAspect('date', 'timestamp')
        ]);
        return true;
    }

    protected function prepareSetup(
        ServerRequestInterface $request,
        MfaProviderPropertyManager $propertyManager,
        string $type
    ): string {
        $webauthn = $this->createWebauthnServer($request, $propertyManager);

        $authenticatorSelectionCriteria = new AuthenticatorSelectionCriteria();
        $authenticatorSelectionCriteria->setUserVerification($this->userVerification);
        $authenticatorSelectionCriteria->setAuthenticatorAttachment($this->authenticatorAttachment);
        $authenticatorSelectionCriteria->setRequireResidentKey(false);

        $userEntity = $this->createUserEntity($propertyManager);

        $publicKeyCredentialSourceRepository = new PublicKeyCredentialSourceRepository($propertyManager);
        $credentialSources = $publicKeyCredentialSourceRepository->findAllForUserEntity($userEntity);
        // Convert the Credential Sources into Public Key Credential Descriptors
        $excludeCredentials = array_map(function (PublicKeyCredentialSource $credential) {
            return $credential->getPublicKeyCredentialDescriptor();
        }, $credentialSources);

        $creationOptions = $webauthn->generatePublicKeyCredentialCreationOptions(
            $userEntity,
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $excludeCredentials,
            $authenticatorSelectionCriteria
        );

        $properties = [
            'creationOptions' => $creationOptions,
            'userEntity' => $userEntity,
        ];
        $propertyManager->hasProviderEntry()
            ? $propertyManager->updateProperties($properties)
            : $propertyManager->createProviderEntry($properties);

        $keys = $propertyManager->getProperty(PublicKeyCredentialSourceRepository::PROPERTY) ?? [];

        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            $pageRenderer->loadJavaScriptModule('@bnf/mfa-webauthn/mfa-web-authn.js');
        } else {
            $pageRenderer->loadRequireJsModule('TYPO3/CMS/MfaWebauthn/MfaWebAuthn');
        }

        $labels = [
            'singular' => 'security key',
            'plural' => 'security keys',
            'defaultName' => 'My Security Token',
        ];

        if ($this->authenticatorAttachment === AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM) {
            $labels = [
                'singular' => 'authenticator',
                'plural' => 'authenticators',
                'defaultName' => 'My Fingerprint',
            ];
        }

        return $this->renderHtmlTag(
            'mfa-webauthn-setup',
            [
                'credential-creation-options' => $creationOptions,
                'credentials' => $keys,
                'mode' => $type,
                'labels' => $labels,
                'locked' => $this->isLocked($propertyManager),
            ]
        );
    }

    private function prepareAuth(ServerRequestInterface $request, MfaProviderPropertyManager $propertyManager): string
    {
        $userEntity = PublicKeyCredentialUserEntity::createFromArray($propertyManager->getProperty('userEntity'));
        $keys = $propertyManager->getProperty(PublicKeyCredentialSourceRepository::PROPERTY);

        $publicKeyCredentialSourceRepository = new PublicKeyCredentialSourceRepository($propertyManager);
        $credentialSources = $publicKeyCredentialSourceRepository->findAllForUserEntity($userEntity);

        // Convert the Credential Sources into Public Key Credential Descriptors
        $allowedCredentials = array_map(function (PublicKeyCredentialSource $credential) {
            return $credential->getPublicKeyCredentialDescriptor();
        }, $credentialSources);

        $webauthn = $this->createWebauthnServer($request, $propertyManager);

        // We generate the set of options.
        $publicKeyCredentialRequestOptions = $webauthn->generatePublicKeyCredentialRequestOptions(
            $this->userVerification,
            $allowedCredentials
        );

        $propertyManager->updateProperties([
            'lastRequest' => $publicKeyCredentialRequestOptions
        ]);

        // @todo: Detect FE
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            $pageRenderer->loadJavaScriptModule('@bnf/mfa-webauthn/mfa-web-authn.js');
        } else {
            $pageRenderer->loadRequireJsModule('TYPO3/CMS/MfaWebauthn/MfaWebAuthn');
        }

        return $this->renderHtmlTag('mfa-webauthn-authenticator', [
            'credential-request-options' => $publicKeyCredentialRequestOptions,
            'locked' => $this->isLocked($propertyManager),
        ]);
    }

    private function getAction(ServerRequestInterface $request): string
    {
        return trim((string)($request->getQueryParams()['webauthn_action'] ?? $request->getParsedBody()['webauthn_action'] ?? ''));
    }

    private function getPublicKey(ServerRequestInterface $request): string
    {
        return trim((string)($request->getQueryParams()['webauthn_publicKeyCredential'] ?? $request->getParsedBody()['webauthn_publicKeyCredential'] ?? ''));
    }

    private function getDescription(ServerRequestInterface $request): string
    {
        return trim((string)($request->getQueryParams()['webauthn_publicKeyDescription'] ?? $request->getParsedBody()['webauthn_publicKeyDescription'] ?? ''));
    }

    private function getIcon(ServerRequestInterface $request): string
    {
        return trim((string)($request->getQueryParams()['webauthn_publicKeyIcon'] ?? $request->getParsedBody()['webauthn_publicKeyIcon'] ?? ''));
    }

    private function createUserEntity(MfaProviderPropertyManager $propertyManager): PublicKeyCredentialUserEntity
    {
        $user = $propertyManager->getUser();
        $userData = $propertyManager->getUser()->user ?? [];
        // @todo: 'email' is not suggested according to
        // https://webauthn-doc.spomky-labs.com/v/v3.3/pre-requisites/user-entity-repository
        $userName = $userData['username'] ?? $userData['email'] ?? '';
        $loginType = $user->loginType ?: 'BE';
        $uniqueid = $loginType . ':' . $userData['uid'];
        $displayName = ($userData['realName'] ?? '') ?: $userName;
        return new PublicKeyCredentialUserEntity($userName, $uniqueid, $displayName);
    }

    private function createWebauthnServer(
        ServerRequestInterface $request,
        MfaProviderPropertyManager $propertyManager
    ): Server {
        $name = 'TYPO3 Backend';
        $id = $this->getNormalizedParams($request)->getRequestHostOnly();

        $server = new Server(
            new PublicKeyCredentialRpEntity($name, $id),
            new PublicKeyCredentialSourceRepository($propertyManager)
        );
        $server->setLogger($this->logger);

        if (preg_match('/^(.+\.)?localhost$/', $id)) {
            // Marks 'localhost' and *.localhost as secure
            // relying party ID (helps for local testing
            $server->setSecuredRelyingPartyId([$id]);
        }

        return $server;
    }

    private function renderHtmlTag(string $tagName, array $attributes = [], string $content = ''): string
    {
        $unescaped = [];
        foreach ($attributes as $name => $value) {
            if (is_object($value) || is_array($value)) {
                $value = GeneralUtility::jsonEncodeForHtmlAttribute($value, false);
            }
            $unescaped[$name] = $value;
        }
        return '<' . $tagName . ' ' . GeneralUtility::implodeAttributes($unescaped, true) . '>' . $content . '</' . $tagName . '>';
    }

    private function getNormalizedParams(ServerRequestInterface $request): NormalizedParams
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        if (!$normalizedParams instanceof NormalizedParams) {
            throw new \InvalidArgumentException('request does not contain normalizedParams attribute', 1679120978);
        }
        return $normalizedParams;
    }
}

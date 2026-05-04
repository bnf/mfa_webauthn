<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2020 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Bnf\MfaWebauthn;

use Bnf\MfaWebauthn\Repository\CredentialRecordRepository;
use Cose\Algorithm\Algorithm;
use Cose\Algorithm\ManagerFactory;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\EdDSA;
use Cose\Algorithm\Signature\RSA;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\AuthenticationExtensions;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;


class Server
{
    /**
     * @var positive-int
     */
    public int $timeout = 60000;

    /**
     * @var positive-int
     */
    public int $challengeSize = 32;

    /**
     * @var ManagerFactory
     */
    private $coseAlgorithmManagerFactory;

    /**
     * @var CredentialRecordRepository
     */
    private $credentialRecordRepository;

    /**
     * @var ExtensionOutputCheckerHandler
     */
    private $extensionOutputCheckerHandler;

    /**
     * @var string[]
     */
    private $selectedAlgorithms;

    public function __construct(
        private readonly PublicKeyCredentialRpEntity $rpEntity,
        /** @var list<string> */
        private readonly array $allowedOrigins,
        private ?LoggerInterface $logger,
    ) {
        $this->coseAlgorithmManagerFactory = new ManagerFactory();
        $this->coseAlgorithmManagerFactory->add('RS1', new RSA\RS1());
        $this->coseAlgorithmManagerFactory->add('RS256', new RSA\RS256());
        $this->coseAlgorithmManagerFactory->add('RS384', new RSA\RS384());
        $this->coseAlgorithmManagerFactory->add('RS512', new RSA\RS512());
        $this->coseAlgorithmManagerFactory->add('PS256', new RSA\PS256());
        $this->coseAlgorithmManagerFactory->add('PS384', new RSA\PS384());
        $this->coseAlgorithmManagerFactory->add('PS512', new RSA\PS512());
        $this->coseAlgorithmManagerFactory->add('ES256', new ECDSA\ES256());
        $this->coseAlgorithmManagerFactory->add('ES256K', new ECDSA\ES256K());
        $this->coseAlgorithmManagerFactory->add('ES384', new ECDSA\ES384());
        $this->coseAlgorithmManagerFactory->add('ES512', new ECDSA\ES512());
        $this->coseAlgorithmManagerFactory->add('Ed25519', new EdDSA\Ed25519());

        $this->selectedAlgorithms = ['RS256', 'RS512', 'PS256', 'PS512', 'ES256', 'ES512', 'Ed25519'];
        $this->extensionOutputCheckerHandler = new ExtensionOutputCheckerHandler();
    }

    public function getCredentialRecordRepository(): CredentialRecordRepository
    {
        return $this->credentialRecordRepository;
    }

    public function setCredentialRecordRepository(CredentialRecordRepository $credentialRecordRepository): void
    {
        $this->credentialRecordRepository = $credentialRecordRepository;
    }

    /**
     * @param string[] $selectedAlgorithms
     */
    public function setSelectedAlgorithms(array $selectedAlgorithms): self
    {
        $this->selectedAlgorithms = $selectedAlgorithms;

        return $this;
    }

    public function addAlgorithm(string $alias, Algorithm $algorithm): self
    {
        $this->coseAlgorithmManagerFactory->add($alias, $algorithm);
        $this->selectedAlgorithms[] = $alias;
        $this->selectedAlgorithms = array_unique($this->selectedAlgorithms);

        return $this;
    }

    public function setExtensionOutputCheckerHandler(ExtensionOutputCheckerHandler $extensionOutputCheckerHandler): self
    {
        $this->extensionOutputCheckerHandler = $extensionOutputCheckerHandler;

        return $this;
    }

    /**
     * @param PublicKeyCredentialDescriptor[] $excludedPublicKeyDescriptors
     */
    public function generatePublicKeyCredentialCreationOptions(PublicKeyCredentialUserEntity $userEntity, ?string $attestationMode = null, array $excludedPublicKeyDescriptors = [], ?AuthenticatorSelectionCriteria $criteria = null, ?AuthenticationExtensions $extensions = null): PublicKeyCredentialCreationOptions
    {
        $coseAlgorithmManager = $this->coseAlgorithmManagerFactory->generate(...$this->selectedAlgorithms);
        $publicKeyCredentialParametersList = [];
        foreach ($coseAlgorithmManager->all() as $algorithm) {
            $publicKeyCredentialParametersList[] = new PublicKeyCredentialParameters(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                $algorithm::identifier()
            );
        }

        return PublicKeyCredentialCreationOptions::create(
            $this->rpEntity,
            $userEntity,
            random_bytes($this->challengeSize),
            $publicKeyCredentialParametersList,
            $criteria,
            $attestationMode ?? PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $excludedPublicKeyDescriptors,
            $this->timeout,
            $extensions,
        );
    }

    /**
     * @param PublicKeyCredentialDescriptor[] $allowedPublicKeyDescriptors
     */
    public function generatePublicKeyCredentialRequestOptions(?string $userVerification = null, array $allowedPublicKeyDescriptors = [], ?AuthenticationExtensions $extensions = null): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            random_bytes($this->challengeSize),
            $this->rpEntity->id,
            $allowedPublicKeyDescriptors,
            $userVerification ?? PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            $this->timeout,
            $extensions,
        );
    }

    public function loadAndCheckAttestationResponse(string $data, PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions, string $hostname): CredentialRecord
    {
        $attestationStatementSupportManager = $this->getAttestationStatementSupportManager();

        $serializer = $this->getSerializer($attestationStatementSupportManager);
        $publicKeyCredential = $serializer->deserialize($data, PublicKeyCredential::class, 'json');
        $authenticatorResponse = $publicKeyCredential->response;
        $authenticatorResponse instanceof AuthenticatorAttestationResponse || throw new \InvalidArgumentException('Not an authenticator attestation response');

        $ceremonyStepManagerFactory = $this->createCeremonyStepManagerFactory($attestationStatementSupportManager);
        $authenticatorAttestationResponseValidator = AuthenticatorAttestationResponseValidator::create(
            $ceremonyStepManagerFactory->creationCeremony()
        );
        if ($this->logger !== null) {
            $authenticatorAttestationResponseValidator->setLogger($this->logger);
        }

        return $authenticatorAttestationResponseValidator->check($authenticatorResponse, $publicKeyCredentialCreationOptions, $hostname);
    }

    public function loadAndCheckAssertionResponse(string $data, PublicKeyCredentialRequestOptions $publicKeyCredentialRequestOptions, ?PublicKeyCredentialUserEntity $userEntity, string $hostname): CredentialRecord
    {
        $attestationStatementSupportManager = $this->getAttestationStatementSupportManager();
        $serializer = $this->getSerializer($attestationStatementSupportManager);

        $publicKeyCredential = $serializer->deserialize($data, PublicKeyCredential::class, 'json');
        $authenticatorResponse = $publicKeyCredential->response;
        $authenticatorResponse instanceof AuthenticatorAssertionResponse || throw new InvalidArgumentException('Not an authenticator assertion response');

        $credentialSource = $this->credentialRecordRepository->findOneByCredentialId($publicKeyCredential->rawId);
        $credentialSource !== null || throw new InvalidArgumentException('Credential source not found');

        $ceremonyStepManagerFactory = $this->createCeremonyStepManagerFactory($attestationStatementSupportManager);
        $authenticatorAssertionResponseValidator = AuthenticatorAssertionResponseValidator::create(
            $ceremonyStepManagerFactory->requestCeremony()
        );
        if ($this->logger !== null) {
            $authenticatorAssertionResponseValidator->setLogger($this->logger);
        }

        $updatedCredentialRecord = $authenticatorAssertionResponseValidator->check(
            $credentialSource,
            $authenticatorResponse,
            $publicKeyCredentialRequestOptions,
            $hostname,
            $userEntity?->id,
        );
        $this->credentialRecordRepository->saveCredentialRecord($updatedCredentialRecord);

        return $updatedCredentialRecord;
    }

    public function getSerializer(?AttestationStatementSupportManager $attestationStatementSupportManager = null): SerializerInterface&NormalizerInterface&DenormalizerInterface
    {
        $attestationStatementSupportManager ??= $this->getAttestationStatementSupportManager();
        $serializer = (new WebauthnSerializerFactory($attestationStatementSupportManager))->create();
        if (!$serializer instanceof NormalizerInterface ||
            !$serializer instanceof DenormalizerInterface
        ) {
            throw new \RuntimeException('Expected WebauthnSerializerFactory to create a (de)normalizing serializer', 1777882044);
        }

        return $serializer;
    }

    private function createCeremonyStepManagerFactory(AttestationStatementSupportManager $attestationStatementSupportManager): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAlgorithmManager($this->coseAlgorithmManagerFactory->generate(...$this->selectedAlgorithms));
        $factory->setAttestationStatementSupportManager($attestationStatementSupportManager);
        $factory->setExtensionOutputCheckerHandler($this->extensionOutputCheckerHandler);
        if ($this->allowedOrigins !== []) {
            $factory->setAllowedOrigins($this->allowedOrigins, true);
        }
        return $factory;
    }

    private function getAttestationStatementSupportManager(): AttestationStatementSupportManager
    {
        $attestationStatementSupportManager = new AttestationStatementSupportManager();
        $attestationStatementSupportManager->add(new FidoU2FAttestationStatementSupport());
        $attestationStatementSupportManager->add(new AndroidKeyAttestationStatementSupport());
        $attestationStatementSupportManager->add(new TPMAttestationStatementSupport());
        $coseAlgorithmManager = $this->coseAlgorithmManagerFactory->generate(...$this->selectedAlgorithms);
        $attestationStatementSupportManager->add(new PackedAttestationStatementSupport($coseAlgorithmManager));

        return $attestationStatementSupportManager;
    }
}

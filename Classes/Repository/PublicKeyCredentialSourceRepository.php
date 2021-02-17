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

namespace Bnf\MfaWebauthn\Repository;

use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webauthn\PublicKeyCredentialSourceRepository as PublicKeyCredentialSourceRepositoryInterface;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

class PublicKeyCredentialSourceRepository implements PublicKeyCredentialSourceRepositoryInterface
{
    public const PROPERTY = 'publicKeyCredentialSources';

    private MfaProviderPropertyManager $propertyManager;

    public function __construct(MfaProviderPropertyManager $mfaProviderPropertyManager)
    {
        $this->propertyManager = $mfaProviderPropertyManager;
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $data = $this->load();
        $identifier = base64_encode($publicKeyCredentialId);
        $source = $data[$identifier]['publickey'] ?? null;
        if ($source === null) {
            return null;
        }

        return PublicKeyCredentialSource::createFromArray($source);
    }

    /**
     * @return PublicKeyCredentialSource[]
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $sources = [];
        foreach ($this->load() as $data) {
            $source = PublicKeyCredentialSource::createFromArray($data['publickey']);
            if ($source->getUserHandle() === $publicKeyCredentialUserEntity->getId()) {
                $sources[] = $source;
            }
        }
        return $sources;
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $identifier = base64_encode($publicKeyCredentialSource->getPublicKeyCredentialId());
        $source = $publicKeyCredentialSource->jsonSerialize();

        $data = $this->load();
        $data[$identifier]['publickey'] = $source;
        $data[$identifier]['updated'] = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        $this->save($data);
    }

    public function addCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource, string $description, string $icon): void
    {
        $identifier = base64_encode($publicKeyCredentialSource->getPublicKeyCredentialId());

        $source = [];
        $source['publickey'] = $publicKeyCredentialSource->jsonSerialize();
        $source['description'] = $description;
        $source['icon'] = $icon;
        $source['created'] = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');

        $data = $this->load();
        $data[$identifier] = $source;
        $this->save($data);
    }

    public function removeCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $identifier = base64_encode($publicKeyCredentialSource->getPublicKeyCredentialId());

        $data = $this->load();
        if (!isset($data[$identifier])) {
            throw new \Exception('Credential source does not exist', 1613413321);
        }
        unset($data[$identifier]);
        $this->save($data);
    }

    private function load(): array
    {
        return $this->propertyManager->getProperty(self::PROPERTY) ?? [];
    }

    private function save(array $data): void
    {
        $properties = [self::PROPERTY => $data];
        $this->propertyManager->hasProviderEntry()
            ? $this->propertyManager->updateProperties($properties)
            : $this->propertyManager->createProviderEntry($properties);
    }
}

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

use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\Serializer;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\CredentialRecordDenormalizer;
use Webauthn\Denormalizer\TrustPathDenormalizer;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

class PublicKeyCredentialSourceRepository
{
    public const PROPERTY = 'publicKeyCredentialSources';

    private MfaProviderPropertyManager $propertyManager;

    public function __construct(MfaProviderPropertyManager $mfaProviderPropertyManager)
    {
        $this->propertyManager = $mfaProviderPropertyManager;
    }

    private static function createSerializer(): Serializer
    {
        return new Serializer([
            new CredentialRecordDenormalizer(),
            new TrustPathDenormalizer(),
            new UidNormalizer(),
            new ArrayDenormalizer(),
        ]);
    }

    /**
     * @param array<string, mixed> $source
     */
    private function createPublicKeyCredentialSource(array $source): CredentialRecord
    {
        return self::createSerializer()->denormalize($source, CredentialRecord::class);
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?CredentialRecord
    {
        $data = $this->load();
        $identifier = base64_encode($publicKeyCredentialId);
        $source = $data[$identifier]['publickey'] ?? null;
        if ($source === null) {
            return null;
        }

        return $this->createPublicKeyCredentialSource($source);
    }

    /**
     * @return CredentialRecord[]
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $sources = [];
        foreach ($this->load() as $data) {
            $source = $this->createPublicKeyCredentialSource($data['publickey']);
            if ($source->userHandle === $publicKeyCredentialUserEntity->id) {
                $sources[] = $source;
            }
        }
        return $sources;
    }

    public function saveCredentialSource(CredentialRecord $publicKeyCredentialSource): void
    {
        $identifier = base64_encode($publicKeyCredentialSource->publicKeyCredentialId);
        /** @var array<string, mixed> $source */
        $source = self::createSerializer()->normalize($publicKeyCredentialSource);

        $data = $this->load();
        $data[$identifier]['publickey'] = $source;
        /** @var int $timestamp */
        $timestamp = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        $data[$identifier]['updated'] = $timestamp;
        $this->save($data);
    }

    public function addCredentialSource(CredentialRecord $publicKeyCredentialSource, string $description, string $icon): void
    {
        $identifier = base64_encode($publicKeyCredentialSource->publicKeyCredentialId);

        $source = [];
        /** @var array<string, mixed> $publickey */
        $publickey = self::createSerializer()->normalize($publicKeyCredentialSource);
        $source['publickey'] = $publickey;
        $source['description'] = $description;
        $source['icon'] = $icon;
        /** @var int $timestamp */
        $timestamp = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        $source['created'] = $timestamp;

        $data = $this->load();
        $data[$identifier] = $source;
        $this->save($data);
    }

    public function removeCredentialSource(CredentialRecord $publicKeyCredentialSource): void
    {
        $identifier = base64_encode($publicKeyCredentialSource->publicKeyCredentialId);

        $data = $this->load();
        if (!isset($data[$identifier])) {
            throw new \Exception('Credential source does not exist', 1613413321);
        }
        unset($data[$identifier]);
        $this->save($data);
    }

    /**
     * @return array<string, array{publickey: array<string, mixed>, description?: string, icon?: string, created?: int, updated?: int}>
     */
    private function load(): array
    {
        /** @var array<string, array{publickey: array<string, mixed>, description?: string, icon?: string, created?: int, updated?: int}> $data */
        $data = $this->propertyManager->getProperty(self::PROPERTY) ?? [];
        return $data;
    }

    /**
     * @param array<string, array{publickey: array<string, mixed>, description?: string, icon?: string, created?: int, updated?: int}> $data
     */
    private function save(array $data): void
    {
        $properties = [self::PROPERTY => $data];
        $this->propertyManager->hasProviderEntry()
            ? $this->propertyManager->updateProperties($properties)
            : $this->propertyManager->createProviderEntry($properties);
    }
}

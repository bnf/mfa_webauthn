<?php

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'tx-mfawebauthn-key' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:mfa_webauthn/Resources/Public/Icons/security-token.svg',
    ],
    'tx-mfawebauthn-fingerprint' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:mfa_webauthn/Resources/Public/Icons/fingerprint.svg',
    ],
];

<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'WebAuthn Provider (FIDO2/U2F) for MFA',
    'description' => 'WebAuthn Provider for TYPO3 Multi Factor Authentication',
    'state' => 'stable',
    'author' => 'Benjamin Franzke',
    'author_email' => 'benjaminfranzke@gmail.com',
    'version' => '1.2.1',
    'clearCacheOnLoad' => true,
    'constraints' => [
        'depends' => [
            'typo3' => '11.2.0-12.4.99',
            'php' => '8.1.0-8.2.99'
        ],
        'suggests' => [
            'sf_yubikey' => '*',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Bnf\\MfaWebauthn\\' => 'Classes/',
        ],
        'classmap' => [
            'Resources/Private/Libraries/',
        ],
    ],
];

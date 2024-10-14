<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'WebAuthn Provider (FIDO2/U2F) for MFA',
    'description' => 'WebAuthn Provider for TYPO3 Multi Factor Authentication',
    'state' => 'stable',
    'author' => 'Benjamin Franzke',
    'author_email' => 'benjaminfranzke@gmail.com',
    'version' => '1.2.4',
    'clearCacheOnLoad' => true,
    'constraints' => [
        'depends' => [
            'typo3' => '11.2.0-13.4.99',
            'php' => '8.1.0-8.4.99'
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

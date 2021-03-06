<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'WebAuthn Provider (FIDO2/U2F) for MFA',
    'description' => 'WebAuthn Provider for TYPO3 Multi Factor Authentication',
    'state' => 'beta',
    'author' => 'Benjamin Franzke',
    'author_email' => 'benjaminfranzke@gmail.com',
    'version' => '0.1.5',
    'clearCacheOnLoad' => true,
    'constraints' => [
        'depends' => [
            'typo3' => '11.1.0-11.1.99',
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

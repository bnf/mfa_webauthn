<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'WebAuthn Provider (FIDO2/U2F) for MFA',
    'description' => 'WebAuthn Provider for TYPO3 Multi Factor Authentication',
    'state' => 'beta',
    'author' => 'Benjamin Franzke',
    'author_email' => 'benjaminfranzke@gmail.com',
    'version' => '0.2.0',
    'clearCacheOnLoad' => true,
    'constraints' => [
        'depends' => [
            'typo3' => '11.2.0-11.4.99',
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

<?php

defined('TYPO3') || die('Access denied.');

\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class)
    ->registerIcon(
        'tx-mfawebauthn-key',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:mfa_webauthn/Resources/Public/Icons/security-token.svg']
    );

\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class)
    ->registerIcon(
        'tx-mfawebauthn-fingerprint',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:mfa_webauthn/Resources/Public/Icons/fingerprint.svg']
    );

$GLOBALS['TYPO3_CONF_VARS']['BE']['recommendedMfaProvider'] = 'webauthn';

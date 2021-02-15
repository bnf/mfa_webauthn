<?php

defined('TYPO3_MODE') || die('Access denied.');

\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class)
    ->registerIcon(
        'tx-mfawebauthn-key',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:mfa_webauthn/Resources/Public/Icons/tx_mfawebauthn_key.svg']
    );

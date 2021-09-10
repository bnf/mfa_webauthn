# WebAuthn Provider for TYPO3 Multi Factor Authentication

This TYPO3 extension integrates into the experimental TYPO3 v11.1 Multi Factor Authentication (MFA) API,
adding authenticators using the [WebAuthn standard](https://webauthn.io). It provides support for
FIDO2/U2F Hardware tokens and Internal Authenticators (e.g. Android Screenlock or Windows hello) as
second factor during authentication.

*This is extensions is provided as a demo and as not intended to stay. It is planned to integrate WebAuthn
support into core in future TYPO3 releases. This extension will be marked abandoned, once that is done.*

## Installation

```
composer require bnf/mfa-webauthn
```

## Prerequisites and Limitations

The WebAuthn API has some design-driven limitations.
Authentication is reserved for secure environments in order to prevent spoofing of credentials,
and therefore a WebAuthn credential is additonally bound to a domain.

This puts the following limitations on usages of this provider:

 * Requires HTTPS or a localhost environment
   (therefore use `http://{myproject}.localhost` as local development URL)
 * Works only for one domain, multi domain sites need to have TYPO3 backend redirected to exactly
   one domain, or should use alternative MFA MFA providers.


## Alternative Extensions

If the restriction to one backend domain is too limiting, consider using [mfa_yubikey](https://github.com/derhansen/mfa_yubikey)
 or [mfa_hotp](https://github.com/o-ba/mfa_hotp) instead. Note, both providers are less secure than webauthn, as the user
can be spoofed with a faked domain name, but they are more flexible and both allow to use hardware tokens with a multi
domain setup.
(`mfa_hotp` is intended for software HOTP authenticators, but the HOTP secret can also be burned to cheap HOTP hardware tokens.)

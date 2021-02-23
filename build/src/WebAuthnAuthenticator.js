import {preparePublicKeyOptions, preparePublicKeyCredentials} from '@web-auth/webauthn-helper/src/common.js';

export class MfaWebauthnAuthenticator extends HTMLElement {
    connectedCallback() {
        const input = document.createElement('input');
        input.setAttribute('type', 'hidden');
        input.setAttribute('name', 'webauthn_publicKeyCredential');
        this.appendChild(input);

        let form = this.parentElement;
        while (form.tagName.toLowerCase() !== 'form') {
            form = form.parentElement;
        }

        const credentialRequestOptions = preparePublicKeyOptions(
            JSON.parse(this.getAttribute('credential-request-options'))
        );

        // Add generic submit listener, allowing the
        // user to (re)trigger verification using the
        // `verify` button
        form.addEventListener('submit', (e) => {
            if (input.value) {
                return;
            }
            e.preventDefault();

            navigator.credentials.get({publicKey: credentialRequestOptions})
                .then(data => {
                    input.value = JSON.stringify(preparePublicKeyCredentials(data));
                    form.requestSubmit();
                }, error => {
                    console.log(error);
                });
        });

        // auto trigger verification once
        form.requestSubmit();
    }
}

window.customElements.define('mfa-webauthn-authenticator', MfaWebauthnAuthenticator);

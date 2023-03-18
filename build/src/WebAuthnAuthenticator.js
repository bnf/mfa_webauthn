import {startAuthentication} from '@simplewebauthn/browser';

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

        // Add generic submit listener, allowing the
        // user to (re)trigger verification using the
        // `verify` button
        form.addEventListener('submit', (e) => {
            if (input.value) {
                return;
            }
            e.preventDefault();

            const options = JSON.parse(this.getAttribute('credential-request-options'));
            startAuthentication(options)
                .then(data => {
                    input.value = JSON.stringify(data);
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

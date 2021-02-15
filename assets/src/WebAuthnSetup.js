import { LitElement, html } from 'lit-element';
import {preparePublicKeyOptions, preparePublicKeyCredentials} from '@web-auth/webauthn-helper/src/common.js';

export class MfaWebauthnSetup extends LitElement {
    static get properties() {
        return {
            mode: {type: String},
            credentials: {type: Object},
            credentialCreationOptions: {type: Object, attribute: 'credential-creation-options'},
            publicKeyCredential: {type: String, attribute: false},
            publicKeyDescription: {type: String, attribute: false},
            action: {type: String, attribute: false},
            loading: {type: Boolean, attribute: false}
        };
    }

    render() {
        const addIcon = this.action === 'add' ? 'fa-check' : (this.loading ? 'fa-circle-o-notch fa-spin' : 'fa-plus');
        return html`
            <input type="hidden" name="webauthn_publicKeyCredential" .value="${this.publicKeyCredential}">
            <input type="hidden" name="webauthn_publicKeyDescription" .value="${this.publicKeyDescription}">
            <input type="hidden" name="webauthn_action" .value="${this.action}">

            ${Object.keys(this.credentials).length === 0 ?
                html`<div class="callout"><h4 class="callout-title">No security keys added</h4><div class="callout-body">Configure security keys below</div></div>` :
                html`
                    <table class="table" style="max-width: 700px; word-break: break-all">
                        <thead>
                            <tr>
                                <th class="col-icon">
                                    <button class="btn btn-default" @click="${this._createCredentials}">
                                        <i class="fa ${addIcon} fa-fw"></i>
                                    </button>
                                </th>
                                <th colspan="2">Registered Security Keys</th>
                            <tr>
                        </thead>
                        <tbody>
                            ${Object.keys(this.credentials).map((prop) => html`
                                <tr id="credential-${prop}">
                                    <td class="col-icon">
                                        <svg width="16" height="37.66" viewBox="0 0 48 113"><path d="M24.53 59.303c6.764.01 12.255-5.488 12.266-12.282.011-6.793-5.463-12.31-12.226-12.32-6.763-.01-12.255 5.488-12.265 12.281-.012 6.793 5.463 12.31 12.226 12.32zm.085-52.347c-2.959-.005-5.36 2.4-5.366 5.371-.004 2.972 2.39 5.384 5.348 5.39 2.958.004 5.36-2.4 5.365-5.372.005-2.972-2.39-5.385-5.347-5.39zM.129 2.6A2.607 2.607 0 012.727 0L45.41.069A2.607 2.607 0 0148 2.679l-.13 80.894a2.607 2.607 0 01-2.597 2.602l-5.2-.01-.038 24.234A2.6 2.6 0 0137.437 113l-27.097-.044a2.6 2.6 0 01-2.59-2.61l.039-24.232-5.2-.008A2.607 2.607 0 010 83.496L.13 2.601z" fill="#000" fill-opacity=".4"/>
                                    </td>

                                    <td class="col-title">
                                        ${this.credentials[prop].description || '(unnamed)'}
                                        <br>
                                        <span class="text-muted">Last used: ${this._formatDate(this.credentials[prop].updated)}</span>
                                    </td>
                                    <td class="col-control">
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-danger" title="Remove key"
                                                @click="${(e) => this._removeCredentials(e, prop)}">
                                                <i class="fa fa-trash fa-fw"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `)}
                        </tbody>
                    </table>
                `
            }

            <button class="btn btn-default btn-lg" @click="${this._createCredentials}">
                <i class="fa ${addIcon} fa-fw"></i>
                Add security key
            </button>
        `;
    }

    createRenderRoot() {
        return this;
    }

    connectedCallback() {
        this.publicKeyCrendetial = '';
        this.publicKeyDescription = '';
        this.action = '';

        this.form = this.parentElement;
        while (this.form.tagName.toLowerCase() !== 'form') {
            this.form = this.form.parentElement;
        }

        super.connectedCallback();

        if (this.mode === 'setup') {
            this._createCredentials();
        }
    }

    _createCredentials(e) {
        e && e.preventDefault();
        const publicKey = preparePublicKeyOptions(JSON.parse(JSON.stringify(this.credentialCreationOptions)))
        this.loading = true;
        navigator.credentials.create({publicKey})
            .then(data => {
                this.action = 'add';
                this.publicKeyCredential = JSON.stringify(preparePublicKeyCredentials(data));
		const description = window.prompt('Please provide a name for this security key.', 'My FIDO2 token');
                this.publicKeyDescription = description;
                this.loading = false;
                this.updateComplete.then(() => this.form.requestSubmit());
            }, error => {
                this.loading = false;
                // User probably aborted or timeout reached
                // @todo: offer retry?/show notice?
                console.log(error);
            });
    }

    _removeCredentials(e, property) {
        e.preventDefault();
        this.action = 'remove';
        const description = this.credentials[property].description || '(unnamed)';
        if (!window.confirm('Do you really want to delete the key "' + description + '"?')) {
            return;
        }
        this.publicKeyCredential = JSON.stringify(this.credentials[property].publickey);
        this.updateComplete.then(() => this.form.requestSubmit());
    }

    _formatDate(timestamp) {
        if (!timestamp) {
            return 'never';
        }
        const date = new Date(timestamp * 1000);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('en-US', options) + ' ' + date.toLocaleTimeString('en-US');
    }
}

window.customElements.define('mfa-webauthn-setup', MfaWebauthnSetup);

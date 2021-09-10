import { LitElement, html } from 'lit';
import {preparePublicKeyOptions, preparePublicKeyCredentials} from '@web-auth/webauthn-helper/src/common.js';

export class MfaWebauthnSetup extends LitElement {
    static get properties() {
        return {
            mode: {type: String},
            credentials: {type: Object},
            credentialCreationOptions: {type: Object, attribute: 'credential-creation-options'},
            labels: {type: Object},
            publicKeyCredential: {type: String, attribute: false},
            publicKeyDescription: {type: String, attribute: false},
            publicKeyIcon: {type: String, attribute: false},
            action: {type: String, attribute: false},
            loading: {type: Boolean, attribute: false}
        };
    }

    render() {
        const addIcon = this.action === 'add' ? 'fa-check' : (this.loading ? 'fa-circle-o-notch fa-spin' : 'fa-plus');
        return html`
            <input type="hidden" name="webauthn_publicKeyCredential" .value="${this.publicKeyCredential}">
            <input type="hidden" name="webauthn_publicKeyDescription" .value="${this.publicKeyDescription}">
            <input type="hidden" name="webauthn_publicKeyIcon" .value="${this.publicKeyIcon}">
            <input type="hidden" name="webauthn_action" .value="${this.action}">

            ${Object.keys(this.credentials).length === 0 ?
                html`<div class="callout" style="margin-top:0"><h4 class="callout-title">No ${this.labels.plural} added</h4><div class="callout-body">Configure ${this.labels.plural} below</div></div>` :
                html`
                    <table class="table" style="max-width: 450px; word-break: break-all">
                        <thead>
                            <tr>
                                <th class="col-icon">
                                    <button class="btn btn-default" @click="${this._createCredentials}">
                                        <i class="fa ${addIcon} fa-fw"></i>
                                    </button>
                                </th>
                                <th colspan="2">Registered ${this.labels.plural}</th>
                            <tr>
                        </thead>
                        <tbody>
                            ${Object.keys(this.credentials).map((prop) => html`
                                <tr id="credential-${prop}">
                                    <td class="col-icon">
                                        ${this._getIcon(this.credentials[prop].icon || 'key')}
                                    </td>

                                    <td class="col-title">
                                        ${this.credentials[prop].description || '(unnamed)'}
                                        <br>
                                        <span class="text-muted">Last used: ${this._formatDate(this.credentials[prop].updated)}</span>
                                    </td>
                                    <td class="col-control">
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-link" title="Remove ${this.labels.singular}"
                                                @click="${(e) => this._removeCredentials(e, prop)}">
                                                ${this._getIcon('trash')}
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
                Add ${this.labels.singular}
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
                this.publicKeyIcon = 'key';
                let defaultName = this.labels.defaultName;
                if (this.credentialCreationOptions.authenticatorSelection.authenticatorAttachment === 'platform') {
                    const isMobile = (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent));
                    defaultName = 'My ' + (isMobile ? 'Phone' : 'Computer');
                    this.publicKeyIcon = isMobile ? 'mobile' : 'computer';
                }
                const description = window.prompt('Please provide a name for this ' + this.labels.signular + '.', defaultName);
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
        if (!window.confirm('Do you really want to delete the ' + this.labels.singular + ' "' + description + '"?')) {
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

    _getIcon(name) {
        if (name === 'mobile') {
            return html`<svg viewBox="0 0 24 24" fill="#999" width="32" height="32"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>`;
        }
        if (name === 'computer') {
            return html`<svg viewBox="0 0 24 24" fill="#999" width="32" height="32"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z"/></svg>`;
        }
        if (name === 'trash') {
            return html`<svg viewBox="0 0 24 24" fill="currentColor" fill-opacity=".8" width="24" height="24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zm2.46-7.12l1.41-1.41L12 12.59l2.12-2.12 1.41 1.41L13.41 14l2.12 2.12-1.41 1.41L12 15.41l-2.12 2.12-1.41-1.41L10.59 14l-2.13-2.12zM15.5 4l-1-1h-5l-1 1H5v2h14V4z"/></svg>`;
        }
        return html`<svg width="32" height="48" viewBox="4 0 16 24" fill="#777"><path d="M8 1c-1.1 0-2 .9-2 2v12.5c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V3c0-1.1-.9-2-2-2zm4 4.416l3 1.334v2c0 1.85-1.28 3.58-3 4-1.72-.42-3-2.15-3-4v-2zm0 .73L9.666 7.184V9.08c.177 1.373 1.094 2.597 2.334 2.98V6.147zM9 18v5h6v-5z"/></svg>`;
    }
}

window.customElements.define('mfa-webauthn-setup', MfaWebauthnSetup);

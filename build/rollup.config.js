// rollup.config.js
import resolve from '@rollup/plugin-node-resolve';
import minifyHTML from 'rollup-plugin-minify-html-literals';
import { terser } from "rollup-plugin-terser";

export default {
    input: 'src/index.js',
    output: [
        {
            file: '../Resources/Public/JavaScript/mfa-web-authn.js',
            format: 'es',
            name: 'webauthn',
            plugins: [terser()]
        },
        {
            file: '../Resources/Public/JavaScript/MfaWebAuthn.js',
            format: 'amd',
            name: 'webauthn',
            plugins: [terser()]
        },
    ],
    plugins: [
        resolve({
            mainFields: ['module', 'main'],
            modulesOnly: true
        }),
        minifyHTML(),
    ],
    external: [ 'lit' ],
}

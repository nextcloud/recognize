/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
import { createAppConfig } from '@nextcloud/vite-config'
import eslint from 'vite-plugin-eslint'
import stylelint from 'vite-plugin-stylelint'

const isProduction = process.env.NODE_ENV === 'production'

export default createAppConfig({
    admin: 'src/admin.js',
}, {
    config: {
        css: {
            modules: {
                localsConvention: 'camelCase',
            },
            preprocessorOptions: {
                scss: {
                    api: 'modern-compiler',
                },
            },
        },
        plugins: [eslint(), stylelint()],
        build: {
            cssCodeSplit: true,
        },
    },
    inlineCSS: { relativeCSSInjection: true },
    minify: isProduction,
})
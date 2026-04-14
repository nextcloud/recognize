/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

module.exports = webpackConfig
webpackConfig.entry.admin = path.join(__dirname, 'src', 'admin.js')

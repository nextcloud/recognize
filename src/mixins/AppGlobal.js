/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

export default {
	methods: {
		t,
		n,
	},
	computed: {
		colorPrimary() {
			return getComputedStyle(document.documentElement).getPropertyValue('--color-primary')
		},
		colorPrimaryLight() {
			return getComputedStyle(document.documentElement).getPropertyValue('--color-primary-light')
		},
		colorPrimaryElement() {
			return getComputedStyle(document.documentElement).getPropertyValue('--color-primary-element')
		},
		colorPrimaryElementLight() {
			return getComputedStyle(document.documentElement).getPropertyValue('--color-primary-element-light')
		},
		colorPrimaryText() {
			return getComputedStyle(document.documentElement).getPropertyValue('--color-primary-text')
		},
		colorMainText() {
			return getComputedStyle(document.documentElement).getPropertyValue('--color-main-text')
		},
		colorMainBackground() {
			return getComputedStyle(document.documentElement).getPropertyValue('--color-main-background')
		},
		colorPlaceholderDark() {
			return getComputedStyle(document.documentElement).getPropertyValue('--color-placeholder-dark')
		},
	},
}

<!--
  - Copyright (c) 2021. The Recognize contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div id="recognize">
		<figure v-if="loading" class="icon-loading loading" />
		<figure v-if="!loading && success" class="icon-checkmark success" />
		<SettingsSection
			:title="t('recognize', 'Status')">
			<p v-if="count >= 0">
				Processed images: {{ count }}<br>
				Unrecognized images: {{ countMissed }}
			</p>
			<p v-else>
				<span class="icon-loading-small" />&nbsp;&nbsp;&nbsp;&nbsp;Counting images
			</p>
			<p>The app is installed and will classify up to 100 images every 10 minutes.</p>
		</SettingsSection>
		<SettingsSection
			:title="t('recognize', 'Manual operation') ">
			<p>To trigger a full classification run manually, run the following command on the terminal.</p>
			<p>&nbsp;</p>
			<pre><code>occ recognize:classify</code></pre>
		</SettingsSection>
		<SettingsSection
			:title="t('recognize', 'GPU Acceleration')">
			<p>
				To make use of GPU acceleration you need <a href="https://www.nvidia.com/Download/index.aspx?lang=en-us"> NVIDIA® GPU drivers >450.x</a>,
				<a href="https://developer.nvidia.com/cuda-toolkit-archive">CUDA® Toolkit 11.2</a>
				and <a href="https://developer.nvidia.com/rdp/cudnn-download">cuDNN SDK 8.1.0</a>.
			</p>
		</SettingsSection>
		<SettingsSection
			:title="t('recognize', 'Reset')">
			<p>Click the below button to remove all tags from all images that have been classified so far.</p>
			<button class="button" @click="onReset">
				Reset tags for classified images
			</button>
		</SettingsSection>
	</div>
</template>

<script>
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import axios from '@nextcloud/axios'

const SETTINGS = ['tensorflow.gpu']

export default {
	name: 'ViewAdmin',
	components: { SettingsSection },
	data() {
		return {
			loading: false,
			success: false,
			error: '',
			count: -1,
			countMissed: -1,
			settings: SETTINGS.reduce((obj, key) => ({ ...obj, [key]: '' }), {}),
			timeout: null,
		}
	},

	watch: {
		error(error) {
			if (!error) return
			OC.Notification.showTemporary(error)
		},
	},
	async created() {
		this.getCount()
		setInterval(() => {
			this.getCount()
		}, 60 * 1000)

		try {
			for (const setting of SETTINGS) {
				this.settings[setting] = await this.getValue(setting)
				if (['true', 'false'].includes(this.settings[setting])) {
					this.settings[setting] = (this.settings[setting] === 'true')
				}
			}
		} catch (e) {
			this.error = this.t('recognize', 'Failed to load settings')
			throw e
		}
	},

	methods: {
		async onReset() {
			this.loading = true
			await axios.get('/index.php/apps/recognize/admin/reset')
			await this.getCount()
			this.loading = false
			this.success = true
			setTimeout(() => {
				this.success = false
			}, 3000)
		},
		async getCount() {
			let resp = await axios.get('/index.php/apps/recognize/admin/count')
			const { count } = resp.data
			resp = await axios.get('/index.php/apps/recognize/admin/countMissed')
			const { count: countMissed } = resp.data
			this.count = count
			this.countMissed = countMissed
		},
		onChange() {
			if (this.timeout) {
				clearTimeout(this.timeout)
			}
			setTimeout(() => {
				this.submit()
			}, 1000)
		},

		async submit() {
			this.loading = true
			for (const setting in this.settings) {
				await this.setValue(setting, this.settings[setting])
			}
			this.loading = false
			this.success = true
			setTimeout(() => {
				this.success = false
			}, 3000)
		},
		async setValue(setting, value) {
			try {
				await new Promise((resolve, reject) =>
					OCP.AppConfig.setValue('recognize', setting, value, {
						success: resolve,
						error: reject,
					})
				)
			} catch (e) {
				this.error = this.t('recognize', 'Failed to save settings')
				throw e
			}
		},

		async getValue(setting) {
			try {
				const resDocument = await new Promise((resolve, reject) =>
					OCP.AppConfig.getValue('recognize', setting, null, {
						success: resolve,
						error: reject,
					})
				)
				if (resDocument.querySelector('status').textContent !== 'ok') {
					this.error = this.t('recognize', 'Failed to load settings')
					console.error('Failed request', resDocument)
					return
				}
				const dataEl = resDocument.querySelector('data')
				return dataEl.firstElementChild.textContent
			} catch (e) {
				this.error = this.t('recognize', 'Failed to load settings')
				throw e
			}
		},
	},
}
</script>
<style>
figure[class^='icon-'] {
	display: inline-block;
}

#recognize {
	position: relative;
}

#recognize .loading,
#recognize .success {
	position: absolute;
	top: 20px;
	right: 20px;
}

#recognize label {
	margin-top: 10px;
	display: flex;
}

#recognize label > * {
	padding: 6px 0;
}

#recognize input[type=text], #recognize input[type=password] {
	width: 50%;
	min-width: 300px;
	display: block;
}

#recognize a:link, #recognize a:visited, #recognize a:hover {
	text-decoration: underline;
}
</style>

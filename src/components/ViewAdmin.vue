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
				Processed files: {{ count }}<br>
				Unrecognized files: {{ countMissed }}
			</p>
			<p v-else>
				<span class="icon-loading-small" />&nbsp;&nbsp;&nbsp;&nbsp;Counting files
			</p>
			<p>The app is installed and will classify up to 100 files every 10 minutes.</p>
		</SettingsSection>
		<SettingsSection
			:title="t('recognize', 'Image tagging')">
			<p>
				<label>
					<input v-model="settings['faces.enabled']" type="checkbox" @change="onChange">
					<span>Enable face recognition</span>
				</label>
			</p>
			<p>
				<label>
					<input v-model="settings['imagenet.enabled']" type="checkbox" @change="onChange">
					<span>Enable object recognition</span>
				</label>
			</p>
			<p style="margin-left: 20px;">
				<label>
					<input v-model="settings['landmarks.enabled']"
						type="checkbox"
						:disabled="!Boolean(settings['imagenet.enabled'])"
						@change="onChange">
					<span>Enable landmark recognition</span>
				</label>
			</p>
		</SettingsSection>
		<SettingsSection
			:title="t('recognize', 'Audio tagging')">
			<p>
				<label>
					<input v-model="settings['musicnn.enabled']" type="checkbox" @change="onChange">
					<span>Enable music genre recognition</span>
				</label>
			</p>
		</SettingsSection>
		<SettingsSection
			:title="t('recognize', 'Manual operation') ">
			<p>To trigger a full classification run manually, run the following commands on the terminal. (The first time, this will download the machine learning model initially, so it will take longer.)</p>
			<p>&nbsp;</p>
			<pre><code>occ recognize:classify-images</code></pre>
			<pre><code>occ recognize:classify-audio</code></pre>
		</SettingsSection>
		<SettingsSection
			:title="t('recognize', 'Tensorflow plain mode')">
			<p>
				If your CPU architecture is not x86 or doesn't support the instructions that tensorflow uses, or your system is not using glibc,
				you can still run tensorflow in JavaScript mode.
			</p>
			<p>
				<label>
					<input v-model="settings['tensorflow.purejs']" type="checkbox" @change="onChange">
					<span>Enable JavaScript mode</span>
				</label>
			</p>
		</SettingsSection>
		<SettingsSection
			:title="t('recognize', 'Node.js path')">
			<p>
				If the shipped Node.js binary doesn't work on your system, because you don't have glibc, you can set the path to a custom node.js binary.
				Currently supported is version v14.17 and newer v14 releases.
			</p>
			<p>
				<input v-model="settings['node_binary']" type="text" @change="onChange">
			</p>
		</SettingsSection>
		<SettingsSection
			:title="t('recognize', 'Reset')">
			<p>Click the below button to remove all tags from all images that have been classified so far.</p>
			<button class="button" @click="onReset">
				Reset tags for classified images
			</button>
		</SettingsSection>
		<SettingsSection
			:title="t('recognize', 'Donate')">
			<p>Work on Recognize is fuelled by a voluntary subscription model. If you like what we do and can spare a few coins each month, please consider donating. Thank you!</p>
			<p>&nbsp;</p>
			<p>
				<a class="button" href="https://www.paypal.me/marcelklehr1">
					Paypal
				</a>
				<a class="button" href="https://liberapay.com/marcelklehr/donate">
					LiberaPay
				</a>
				<a class="button" href="https://github.com/sponsors/marcelklehr">
					Github Sponsors
				</a>
			</p>
		</SettingsSection>
	</div>
</template>

<script>
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const SETTINGS = ['tensorflow.gpu', 'tensorflow.purejs', 'imagenet.enabled', 'landmarks.enabled', 'faces.enabled', 'musicnn.enabled', 'node_binary']

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
			await axios.get(generateUrl('/apps/recognize/admin/reset'))
			await this.getCount()
			this.loading = false
			this.success = true
			setTimeout(() => {
				this.success = false
			}, 3000)
		},
		async getCount() {
			let resp = await axios.get(generateUrl('/apps/recognize/admin/count'))
			const { count } = resp.data
			resp = await axios.get(generateUrl('/apps/recognize/admin/countMissed'))
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

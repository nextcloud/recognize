<!--
  - Copyright (c) 2021. The Recognize contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div id="recognize">
		<figure v-if="loading" class="icon-loading loading" />
		<figure v-if="!loading && success" class="icon-checkmark success" />
		<SettingsSection :title="t('recognize', 'Status')">
			<template v-if="settings['faces.enabled'] || settings['geo.enabled'] || settings['imagenet.enabled'] || settings['musicnn.enabled']">
				<p>The app is installed and will automatically classify up to 100 files every 30 minutes.</p>
				<template v-if="settings['faces.enabled'] || settings['geo.enabled'] || settings['imagenet.enabled']">
					<p v-if="settings['images.status'] === true">
						Image recognition is working.
					</p>
					<p v-else-if="settings['images.status'] === false">
						An error occurred during image processing, please check the Nextcloud logs.
					</p>
					<p v-else>
						<span class="icon-loading-small" />&nbsp;&nbsp;&nbsp;&nbsp;Waiting for status reports on image processing. If this message persists beyond 30 minutes, please check the Nextcloud logs.
					</p>
				</template>
				<template v-if="settings['musicnn.enabled']">
					<p v-if="settings['audio.status'] === true">
						Audio recognition is working.
					</p>
					<p v-else-if="settings['audio.status'] === false">
						An error occurred during audio recognition, please check the Nextcloud logs.
					</p>
					<p v-else>
						<span class="icon-loading-small" />&nbsp;&nbsp;&nbsp;&nbsp;Waiting for status reports on audio recognition. If this message persists beyond 30 minutes, please check the Nextcloud logs.
					</p>
				</template>
				<template v-if="settings['movinet.enabled']">
					<p v-if="settings['video.status'] === true">
						Video recognition is working.
					</p>
					<p v-else-if="settings['video.status'] === false">
						An error occurred during video recognition, please check the Nextcloud logs.
					</p>
					<p v-else>
						<span class="icon-loading-small" />&nbsp;&nbsp;&nbsp;&nbsp;Waiting for status reports on video recognition. If this message persists beyond 30 minutes, please check the Nextcloud logs.
					</p>
				</template>
				<p v-if="count >= 0">
					Processed files: {{ count }}<br>
					Unrecognized files: {{ countMissed }}
				</p>
				<p v-else>
					<span class="icon-loading-small" />&nbsp;&nbsp;&nbsp;&nbsp;Counting files
				</p>
			</template>
			<p v-else>
				None of the tagging options below are currently selected. The app will currently do nothing.
			</p>
		</SettingsSection>
		<SettingsSection :title="t('recognize', 'Image tagging')">
			<p>
				<label>
					<input v-model="settings['faces.enabled']" type="checkbox" @change="onChange">
					<span>Enable face recognition (based on contact pictures in the Contacts app)</span>
				</label>
			</p>
			<p>
				<label>
					<input v-model="settings['geo.enabled']" type="checkbox" @change="onChange">
					<span>Enable location tagging based on GPS tags (country only tags, e.g. Spain, South Africa)</span>
				</label>
			</p>
			<p>
				<label>
					<input v-model="settings['imagenet.enabled']" type="checkbox" @change="onChange">
					<span>Enable object recognition (e.g. food, vehicles, landscapes)</span>
				</label>
			</p>
			<p class="indent">
				<label>
					<input v-model="settings['landmarks.enabled']"
						type="checkbox"
						:disabled="!Boolean(settings['imagenet.enabled'])"
						@change="onChange">
					<span>Enable landmark recognition (e.g. Eiffel Tower, Golden Gate Bridge)</span>
				</label>
			</p>
		</SettingsSection>
		<SettingsSection :title="t('recognize', 'Audio tagging')">
			<p>
				<label>
					<input v-model="settings['musicnn.enabled']" type="checkbox" @change="onChange">
					<span>Enable music genre recognition (e.g. pop, rock, folk, metal, new age)</span>
				</label>
			</p>
		</SettingsSection>
		<SettingsSection :title="t('recognize', 'Video tagging')">
			<p>
				<label>
					<input v-model="settings['movinet.enabled']"
						type="checkbox"
						:disabled="platform !== 'x86_64' || settings['tensorflow.purejs']"
						@change="onChange">
					<span>Enable human action recognition (e.g. arm wrestling, dribbling basketball, hula hooping)</span>
				</label>
			</p>
		</SettingsSection>
		<SettingsSection :title="t('recognize', 'Reset')">
			<p>Click the below button to remove all tags from all files that have been classified so far.</p>
			<button class="button" @click="onReset">
				Reset tags for classified files
			</button>
			<p>Click the below button to rescan all files in this instance and add them to the classifier queues.</p>
			<button class="button" @click="onRescan">
				Rescan all files
			</button>
		</SettingsSection>
		<SettingsSection :title="t('recognize', 'Manual operation') ">
			<p>To trigger a full classification run manually, run the following commands on the terminal. (The first time, this will download the machine learning model initially, so it will take longer.)</p>
			<pre><code>occ recognize:classify-images</code></pre>
			<pre><code>occ recognize:classify-audio</code></pre>
			<pre><code>occ recognize:classify-video</code></pre>
			<p>&nbsp;</p>
			<p>You can reset the tags of all files that have been previously classified by recognize with the following command:</p>
			<pre><code>occ recognize:reset-tags</code></pre>
			<p>&nbsp;</p>
			<p>You can delete all tags that no longer have any files associated with them with the following command:</p>
			<pre><code>occ recognize:cleanup-tags</code></pre>
		</SettingsSection>
		<SettingsSection :title="t('recognize', 'CPU cores') ">
			<p>By default all available CPU cores will be used which may put your system under considerable load. To avoid this, you can limit the amount of CPU Cores used.</p>
			<p>
				<label>
					<input v-model="settings['tensorflow.cores']"
						type="number"
						:min="0"
						:step="1"
						:max="32"
						@change="onChange">
					<span>Number of CPU Cores (0 for no limit)</span>
				</label>
			</p>
		</SettingsSection>
		<SettingsSection :title="t('recognize', 'Tensorflow plain mode')">
			<p v-if="avx === null || platform === null || musl === null">
				<span class="icon-loading-small" />&nbsp;&nbsp;&nbsp;&nbsp;Checking CPU
			</p>
			<p v-else-if="avx && platform === 'x86_64' && !musl">
				Your machine supports native TensorFlow operation, you do not need WASM mode.
			</p>
			<p v-else>
				Your machine does not support native TensorFlow operation, because {{ pureJSReasons }}. WASM mode is recommended.
			</p>
			<p>
				<label>
					<input v-model="settings['tensorflow.purejs']" type="checkbox" @change="onChange">
					<span>Enable WASM mode</span>
				</label>
			</p>
		</SettingsSection>
		<SettingsSection :title="t('recognize', 'Node.js path')">
			<p>
				If the shipped Node.js binary doesn't work on your system for some reason you can set the path to a custom node.js binary.
				Currently supported is Node v14.17 and newer v14 releases.
			</p>
			<p>
				<input v-model="settings['node_binary']" type="text" @change="onChange">
			</p>
		</SettingsSection>
	</div>
</template>

<script>
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'

const SETTINGS = ['tensorflow.cores', 'tensorflow.gpu', 'tensorflow.purejs', 'geo.enabled', 'imagenet.enabled', 'landmarks.enabled', 'faces.enabled', 'musicnn.enabled', 'movinet.enabled', 'node_binary', 'audio.status', 'images.status', 'video.status']

const BOOLEAN_SETTINGS = ['tensorflow.gpu', 'tensorflow.purejs', 'geo.enabled', 'imagenet.enabled', 'landmarks.enabled', 'faces.enabled', 'musicnn.enabled', 'movinet.enabled', 'audio.status', 'images.status', 'video.status']

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
			avx: null,
			platform: null,
			musl: null,
		}
	},

	computed: {
		pureJSReasons() {
			const reasons = []
			if (!this.avx) {
				reasons.push('it does not support AVX instructions')
			}
			if (this.platform !== 'x86_64') {
				reasons.push('it doesn\'t have not an x86 64bit CPU')
			}
			if (this.musl) {
				reasons.push('it uses musl libc')
			}
			return reasons.join(' and ')
		},
	},

	watch: {
		error(error) {
			if (!error) return
			OC.Notification.showTemporary(error)
		},
	},
	async created() {
		this.getCount()
		this.getAVX()
		this.getPlatform()
		this.getMusl()

		setInterval(async () => {
			this.getCount()
			this.loadValue('audio.status')
			this.loadValue('images.status')
		}, 5 * 60 * 1000)

		try {
			const settings = loadState('recognize', 'settings')
			for (const setting of SETTINGS) {
				this.settings[setting] = settings[setting]
				if (BOOLEAN_SETTINGS.includes(setting)) {
					this.settings[setting] = (this.settings[setting] === 'true')
				}
				if (setting === 'tensorflow.cores' && this.settings[setting] === '') {
					this.settings[setting] = 0
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
		async onRescan() {
			this.loading = true
			await axios.get(generateUrl('/apps/recognize/admin/recrawl'))
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
		async getAVX() {
			const resp = await axios.get(generateUrl('/apps/recognize/admin/avx'))
			const { avx } = resp.data
			this.avx = avx
		},
		async getPlatform() {
			const resp = await axios.get(generateUrl('/apps/recognize/admin/platform'))
			const { platform } = resp.data
			this.platform = platform
		},
		async getMusl() {
			const resp = await axios.get(generateUrl('/apps/recognize/admin/musl'))
			const { musl } = resp.data
			this.musl = musl
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

		async loadValue(setting) {
			this.settings[setting] = await this.getValue(setting)
			if (BOOLEAN_SETTINGS.includes(setting)) {
				this.settings[setting] = (this.settings[setting] === 'true')
			}
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

#recognize .indent {
	margin-left: 20px;
}

#recognize .loading,
#recognize .success {
	position: fixed;
	top: 70px;
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

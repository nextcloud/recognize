<!--
  - Copyright (c) 2021. The Recognize contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div id="recognize">
		<figure v-if="loading" class="icon-loading loading" />
		<figure v-if="!loading && success" class="icon-checkmark success" />
		<NcSettingsSection :title="t('recognize', 'Status')">
			<NcNoteCard v-if="modelsDownloaded" show-alert type="success">
				{{ t('recognize', 'The machine learning models have been downloaded successfully.') }}
			</NcNoteCard>
			<NcNoteCard v-else-if="!modelsDownloaded" show-alert type="success">
				{{ t('recognize', 'The machine learning models still need to be downloaded.') }}
			</NcNoteCard>
			<NcNoteCard v-if="nodejs === false">
				{{ t('recognize', 'Could not execute the Node.js binary. You may need to set the path to a working binary manually.') }}
			</NcNoteCard>
			<template v-else-if="settings['faces.enabled'] || settings['imagenet.enabled'] || settings['musicnn.enabled'] || settings['movinet.enabled']">
				<NcNoteCard show-alert type="success">
					{{ t('recognize', 'The app is installed and will automatically classify files in background processes.') }}
				</NcNoteCard>
			</template>
			<p v-else>
				{{ t('recognize', 'None of the tagging options below are currently selected. The app will currently do nothing.') }}
			</p>
		</NcSettingsSection>
		<NcSettingsSection :title="t('recognize', 'Image tagging')">
			<template v-if="settings['faces.enabled']">
				<NcNoteCard v-if="settings['faces.status'] === true" show-alert type="success">
					{{ t('recognize', 'Face recognition is working. ') }}
				</NcNoteCard>
				<NcNoteCard v-else-if="settings['faces.status'] === false" show-alert type="error">
					{{ t('recognize', 'An error occurred during face recognition, please check the Nextcloud logs.') }}
				</NcNoteCard>
				<NcNoteCard v-else>
					{{ t('recognize', 'Waiting for status reports on face recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs.') }}
				</NcNoteCard>
				<NcNoteCard v-if="countQueued">
					{{ t('recognize', 'Face recognition:') }} {{ countQueued.faces }} {{ t('recognize', 'Queued files') }}, {{ t('recognize', 'Last classification: ') }} {{ showDate(settings['faces.lastFile']) }}
				</NcNoteCard>
			</template>
			<template v-if="settings['imagenet.enabled']">
				<NcNoteCard v-if="settings['imagenet.status'] === true" show-alert type="success">
					{{ t('recognize', 'Object recognition is working.') }}
				</NcNoteCard>
				<NcNoteCard v-else-if="settings['imagenet.status'] === false" show-alert type="error">
					{{ t('recognize', 'An error occurred during object recognition, please check the Nextcloud logs.') }}
				</NcNoteCard>
				<NcNoteCard v-else>
					{{ t('recognize', 'Waiting for status reports on object recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs.') }}
				</NcNoteCard>
				<NcNoteCard v-if="countQueued">
					{{ t('recognize', 'Object recognition:') }} {{ countQueued.imagenet }} {{ t('recognize', 'Queued files') }}, {{ t('recognize', 'Last classification: ') }} {{ showDate(settings['imagenet.lastFile']) }}
				</NcNoteCard>
			</template>
			<p>
				<NcCheckboxRadioSwitch :checked.sync="settings['faces.enabled']" type="switch" @update:checked="onChange">
					{{ t('recognize', 'Enable face recognition (groups pictures by people that appear in them in the photos app)') }}
				</NcCheckboxRadioSwitch>
				<NcTextField :disabled="!settings['faces.enabled']"
					:value.sync="settings['faces.batchSize']"
					:label-visible="true"
					:label="t('recognize', 'The number of files to process per job run (A job will be scheduled every 5 minutes; For normal operation ~500 or more, in WASM mode ~50 is recommended)')"
					@update:value="onChange" />
			</p>
			<p>&nbsp;</p>
			<p>
				<NcCheckboxRadioSwitch :checked.sync="settings['imagenet.enabled']" type="switch" @update:checked="onChange">
					{{ t('recognize', 'Enable object recognition (e.g. food, vehicles, landscapes)') }}
				</NcCheckboxRadioSwitch>
				<NcTextField :disabled="!settings['imagenet.enabled']"
					:value.sync="settings['imagenet.batchSize']"
					:label-visible="true"
					:label="t('recognize', 'The number of files to process per job run (A job will be scheduled every 5 minutes; For normal operation ~100 or more, in WASM mode ~20 is recommended)')"
					@update:value="onChange" />
			</p>
			<p>&nbsp;</p>
			<p>
				<NcCheckboxRadioSwitch :checked.sync="settings['landmarks.enabled']"
					type="switch"
					:disabled="!settings['imagenet.enabled']"
					@update:checked="onChange">
					{{ t('recognize', 'Enable landmark recognition (e.g. Eiffel Tower, Golden Gate Bridge)') }}
				</NcCheckboxRadioSwitch>
				<NcTextField :disabled="!settings['imagenet.enabled'] || !settings['landmarks.enabled']"
					:value.sync="settings['landmarks.batchSize']"
					:label-visible="true"
					:label="t('recognize', 'The number of files to process per job run (A job will be scheduled every 5 minutes; For normal operation ~100 or more, in WASM mode ~20 is recommended)')"
					@update:value="onChange" />
			</p>
		</NcSettingsSection>
		<NcSettingsSection :title="t('recognize', 'Audio tagging')">
			<template v-if="settings['musicnn.enabled']">
				<NcNoteCard v-if="settings['musicnn.status'] === true" show-alert type="success">
					{{ t('recognize', 'Audio recognition is working.') }}
				</NcNoteCard>
				<NcNoteCard v-else-if="settings['musicnn.status'] === false" show-alert type="error">
					{{ t('recognize', 'An error occurred during audio recognition, please check the Nextcloud logs.') }}
				</NcNoteCard>
				<NcNoteCard v-else>
					{{ t('recognize', 'Waiting for status reports on audio recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs.') }}
				</NcNoteCard>
				<NcNoteCard v-if="countQueued">
					{{ t('recognize', 'Music genre recognition:') }} {{ countQueued.musicnn }} {{ t('recognize', 'Queued files') }}, {{ t('recognize', 'Last classification: ') }} {{ showDate(settings['musicnn.lastFile']) }}
				</NcNoteCard>
			</template>
			<p>
				<NcCheckboxRadioSwitch :checked.sync="settings['musicnn.enabled']" type="switch" @update:checked="onChange">
					{{ t('recognize', 'Enable music genre recognition (e.g. pop, rock, folk, metal, new age)') }}
				</NcCheckboxRadioSwitch>
				<NcTextField :disabled="!settings['musicnn.enabled']"
					:value.sync="settings['musicnn.batchSize']"
					:label-visible="true"
					:label="t('recognize', 'The number of files to process per job run (A job will be scheduled every 5 minutes; For normal operation ~100 or more, in WASM mode ~20 is recommended)')"
					@update:value="onChange" />
			</p>
		</NcSettingsSection>
		<NcSettingsSection :title="t('recognize', 'Video tagging')">
			<template v-if="settings['movinet.enabled']">
				<NcNoteCard v-if="settings['movinet.status'] === true" show-alert type="success">
					{{ t('recognize', 'Video recognition is working.') }}
				</NcNoteCard>
				<NcNoteCard v-else-if="settings['movinet.status'] === false" show-alert type="error">
					{{ t('recognize', 'An error occurred during video recognition, please check the Nextcloud logs.') }}
				</NcNoteCard>
				<NcNoteCard v-else>
					{{ t('recognize', 'Waiting for status reports on video recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs.') }}
				</NcNoteCard>
				<NcNoteCard v-if="countQueued">
					{{ t('recognize', 'Video recognition:') }} {{ countQueued.movinet }} {{ t('recognize', 'Queued files') }}, {{ t('recognize', 'Last classification: ') }} {{ showDate(settings['movinet.lastFile']) }}
				</NcNoteCard>
			</template>
			<p>
				<NcCheckboxRadioSwitch :checked.sync="settings['movinet.enabled']"
					type="switch"
					:disabled="platform !== 'x86_64' || settings['tensorflow.purejs']"
					@update:checked="onChange">
					{{ t('recognize', 'Enable human action recognition (e.g. arm wrestling, dribbling basketball, hula hooping)') }}
				</NcCheckboxRadioSwitch>
				<NcTextField :disabled="!settings['movinet.enabled']"
					:value.sync="settings['movinet.batchSize']"
					:label-visible="true"
					:label="t('recognize', 'The number of files to process per job run (A job will be scheduled every 5 minutes; For normal operation ~20 or more, in WASM mode ~5 is recommended)')"
					@update:value="onChange" />
			</p>
		</NcSettingsSection>
		<NcSettingsSection :title="t('recognize', 'Reset')">
			<p>{{ t('recognize', 'Click the button below to remove all tags from all files that have been classified so far.') }}</p>
			<button class="button" @click="onReset">
				{{ t('recognize', 'Reset tags for classified files') }}
			</button>
			<p>&nbsp;</p>
			<p>{{ t('recognize', 'Click the button below to remove all face detections from all files that have been classified so far.') }}</p>
			<button class="button" @click="onResetFaces">
				{{ t('recognize', 'Reset faces for classified files') }}
			</button>
			<p>&nbsp;</p>
			<p>{{ t('recognize', 'Click the button below to rescan all files in this instance and add them to the classifier queues.') }}</p>
			<button class="button" @click="onRescan">
				{{ t('recognize', 'Rescan all files') }}
			</button>
		</NcSettingsSection>
		<NcSettingsSection :title="t('recognize', 'Terminal commands') ">
			<p>{{ t('recognize', 'To trigger a full classification run manually, run the following command on the server terminal.') }}</p>
			<pre><code>occ recognize:recrawl</code></pre>
			<p>&nbsp;</p>
			<p>{{ t('recognize', 'To download all models preliminary to executing the classification jobs, run the following command on the server terminal.') }}</p>
			<pre><code>occ recognize:download-models</code></pre>
			<p>&nbsp;</p>
			<p>{{ t('recognize', 'You can reset the tags of all files that have been previously classified by Recognize with the following command:') }}</p>
			<pre><code>occ recognize:reset-tags</code></pre>
			<p>&nbsp;</p>
			<p>{{ t('recognize', 'You can delete all tags that no longer have any files associated with them with the following command:') }}</p>
			<pre><code>occ recognize:cleanup-tags</code></pre>
		</NcSettingsSection>
		<NcSettingsSection :title="t('recognize', 'CPU cores') ">
			<p>{{ t('recognize', 'By default all available CPU cores will be used which may put your system under considerable load. To avoid this, you can limit the amount of CPU Cores used.') }}</p>
			<p>
				<label>
					<input v-model="settings['tensorflow.cores']"
						type="number"
						:min="0"
						:step="1"
						:max="32"
						@change="onChange">
					<span>{{ t('recognize', 'Number of CPU Cores (0 for no limit)') }}</span>
				</label>
			</p>
		</NcSettingsSection>
		<NcSettingsSection :title="t('recognize', 'Tensorflow WASM mode')">
			<p v-if="avx === undefined || platform === undefined || musl === undefined">
				<span class="icon-loading-small" />&nbsp;&nbsp;&nbsp;&nbsp;{{ t('recognize', 'Checking CPU') }}
			</p>
			<NcNoteCard v-else-if="avx === null || platform === null || musl === null">
				{{ t('recognize', 'Could not check whether your machine supports native TensorFlow operation.') }}
			</NcNoteCard>
			<NcNoteCard v-else-if="avx && platform === 'x86_64' && !musl" type="success">
				{{ t('recognize', 'Your machine supports native TensorFlow operation, you do not need WASM mode.') }}
			</NcNoteCard>
			<template v-else>
				<p>
					{{ t('recognize', 'WASM mode was activated automatically, because your machine does not support native TensorFlow operation:') }}
				</p>
				<ul>
					<li v-for="reason in pureJSReasons" :key="reason">
						{{ reason }}
					</li>
				</ul>
			</template>
			<p>
				<NcCheckboxRadioSwitch :checked.sync="settings['tensorflow.purejs']" type="switch" @update:checked="onChange">
					{{ t('recognize', 'Enable WASM mode') }}
				</NcCheckboxRadioSwitch>
			</p>
		</NcSettingsSection>
		<NcSettingsSection :title="t('recognize', 'Node.js')">
			<p v-if="nodejs === undefined">
				<span class="icon-loading-small" />&nbsp;&nbsp;&nbsp;&nbsp;{{ t('recognize', 'Checking Node.js') }}
			</p>
			<NcNoteCard v-else-if="nodejs === false">
				{{ t('recognize', 'Could not execute the Node.js binary. You may need to set the path to a working binary manually.') }}
			</NcNoteCard>
			<NcNoteCard v-else type="success">
				{{ t('recognize', 'Node.js {version} binary was installed successfully.', { version: nodejs }) }}
			</NcNoteCard>
			<p v-if="libtensorflow === undefined || wasmtensorflow === undefined">
				<span class="icon-loading-small" />&nbsp;&nbsp;&nbsp;&nbsp;{{ t('recognize', 'Checking libtensorflow') }}
			</p>
			<template v-if="settings['tensorflow.purejs'] === false">
				<NcNoteCard v-if="nodejs !== false && libtensorflow === false">
					{{ t('recognize', 'Could not load libtensorflow in Node.js. You can try to manually install libtensorflow or run in WASM mode.') }}
				</NcNoteCard>
				<NcNoteCard v-else-if="libtensorflow === true" type="success">
					{{ t('recognize', 'Libtensorflow was loaded successfully into Node.js.') }}
				</NcNoteCard>
			</template>
			<template v-else>
				<NcNoteCard v-if="nodejs !== false && wasmtensorflow === false">
					{{ t('recognize', 'Could not load Tensorflow WASM in Node.js. Something is wrong with your setup.') }}
				</NcNoteCard>
				<NcNoteCard v-else-if="wasmtensorflow === true" type="success">
					{{ t('recognize', 'Tensorflow WASM was loaded successfully into Node.js.') }}
				</NcNoteCard>
			</template>
			<p>
				{{ t('recognize', 'If the shipped Node.js binary doesn\'t work on your system for some reason you can set the path to a custom node.js binary. Currently supported is Node v14.17 and newer v14 releases.') }}
			</p>
			<p>
				<input v-model="settings['node_binary']" type="text" @change="onChange">
			</p>
		</NcSettingsSection>
	</div>
</template>

<script>
import { NcNoteCard, NcSettingsSection, NcCheckboxRadioSwitch, NcTextField } from '@nextcloud/vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import humanizeDuration from 'humanize-duration'

const SETTINGS = ['tensorflow.cores', 'tensorflow.gpu', 'tensorflow.purejs', 'imagenet.enabled', 'landmarks.enabled', 'faces.enabled', 'musicnn.enabled', 'movinet.enabled', 'node_binary', 'faces.status', 'imagenet.status', 'landmarks.status', 'movinet.status', 'musicnn.status', 'faces.lastFile', 'imagenet.lastFile', 'landmarks.lastFile', 'movinet.lastFile', 'musicnn.lastFile', 'faces.batchSize', 'imagenet.batchSize', 'landmarks.batchSize', 'movinet.batchSize', 'musicnn.batchSize']

const BOOLEAN_SETTINGS = ['tensorflow.gpu', 'tensorflow.purejs', 'imagenet.enabled', 'landmarks.enabled', 'faces.enabled', 'musicnn.enabled', 'movinet.enabled', 'faces.status', 'imagenet.status', 'landmarks.status', 'movinet.status', 'musicnn.status', 'faces.lastFile', 'imagenet.lastFile', 'landmarks.lastFile', 'movinet.lastFile', 'musicnn.lastFile']

const MAX_RELATIVE_DATE = 1000 * 60 * 60 * 24 * 7 // one week

export default {
	name: 'ViewAdmin',
	components: { NcSettingsSection, NcNoteCard, NcCheckboxRadioSwitch, NcTextField },

	data() {
		return {
			loading: false,
			success: false,
			error: '',
			count: -1,
			countQueued: null,
			settings: SETTINGS.reduce((obj, key) => ({ ...obj, [key]: '' }), {}),
			timeout: null,
			avx: undefined,
			platform: undefined,
			musl: undefined,
			nodejs: undefined,
			libtensorflow: undefined,
			wasmtensorflow: undefined,
			modelsDownloaded: null,
		}
	},

	computed: {
		pureJSReasons() {
			const reasons = []
			if (!this.avx) {
				reasons.push(this.t('recognize', 'Your server does not support AVX instructions'))
			}
			if (this.platform !== 'x86_64') {
				reasons.push(this.t('recognize', 'Your server does not have an x86 64-bit CPU'))
			}
			if (this.musl) {
				reasons.push(this.t('recognize', 'Your server uses musl libc'))
			}
			return reasons
		},
	},

	watch: {
		error(error) {
			if (!error) return
			OC.Notification.showTemporary(error)
		},
	},
	async created() {
		this.modelsDownloaded = loadState('recognize', 'modelsDownloaded')
		this.getCount()
		this.getAVX()
		this.getPlatform()
		this.getMusl()
		this.getNodejsStatus()
		this.getLibtensorflowStatus()
		this.getWasmtensorflowStatus()

		setInterval(async () => {
			this.getCount()
			this.loadValue('imagenet.status')
			this.loadValue('faces.status')
			this.loadValue('landmarks.status')
			this.loadValue('movinet.status')
			this.loadValue('musicnn.status')
		}, 5 * 60 * 1000)

		try {
			const settings = loadState('recognize', 'settings')
			for (const setting of SETTINGS) {
				this.settings[setting] = settings[setting]
				if (BOOLEAN_SETTINGS.includes(setting)) {
					this.settings[setting] = JSON.parse(this.settings[setting])
				}
				if (setting.includes('batchSize') && (this.settings[setting] === '' || settings[setting] === 'null')) {
					this.settings[setting] = 100
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
		async onResetFaces() {
			this.loading = true
			await axios.get(generateUrl('/apps/recognize/admin/resetFaces'))
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
			const resp = await axios.get(generateUrl('/apps/recognize/admin/countQueued'))
			this.countQueued = resp.data
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
		async getNodejsStatus() {
			const resp = await axios.get(generateUrl('/apps/recognize/admin/nodejs'))
			const { nodejs } = resp.data
			this.nodejs = nodejs
		},
		async getLibtensorflowStatus() {
			const resp = await axios.get(generateUrl('/apps/recognize/admin/libtensorflow'))
			const { libtensorflow } = resp.data
			this.libtensorflow = libtensorflow
		},
		async getWasmtensorflowStatus() {
			const resp = await axios.get(generateUrl('/apps/recognize/admin/wasmtensorflow'))
			const { wasmtensorflow } = resp.data
			this.wasmtensorflow = wasmtensorflow
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
				if (setting.includes('status') || setting.includes('lastFile')) {
					continue
				}
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
				this.settings[setting] = JSON.parse(this.settings[setting])
			}
		},
		async setValue(setting, value) {
			try {
				if (BOOLEAN_SETTINGS.includes(setting)) {
					value = JSON.stringify(value)
				}
				await axios.put(generateUrl(`/apps/recognize/admin/settings/${setting}`), {
					value,
				})
			} catch (e) {
				this.error = this.t('recognize', 'Failed to save settings')
				throw e
			}
		},

		async getValue(setting) {
			try {
				const res = await axios.get(generateUrl(`/apps/recognize/admin/settings/${setting}`))
				if (res.status !== 200) {
					this.error = this.t('recognize', 'Failed to load settings')
					console.error('Failed request', res)
					return
				}
				return res.data.value
			} catch (e) {
				this.error = this.t('recognize', 'Failed to load settings')
				throw e
			}
		},

		showDate(timestamp) {
			if (timestamp === null) {
				return this.t('recognize', 'never')
			}
			const date = new Date(Number(timestamp) * 1000)
			const age = Date.now() - date
			if (age < MAX_RELATIVE_DATE) {
				const duration = humanizeDuration(age, {
					language: OC.getLanguage().split('-')[0],
					units: ['d', 'h', 'm', 's'],
					largest: 1,
					round: true,
				})
				return this.t('recognize', '{time} ago', { time: duration })
			} else {
				return date.toLocaleDateString()
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
	padding: 8px 0;
	padding-left: 6px;
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

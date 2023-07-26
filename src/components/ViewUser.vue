<!--
  - Copyright (c) 2021. The Recognize contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div id="recognize">
		<figure v-if="loading" class="icon-loading loading" />
		<figure v-if="!loading && successTick" class="icon-checkmark success" />
		<NcSettingsSection :title="t('recognize', 'Status')">
			<NcNoteCard v-if="modelsDownloaded" show-alert type="success">
				{{ t('recognize', 'The machine learning models have been downloaded successfully.') }}
			</NcNoteCard>
			<NcNoteCard v-else-if="!modelsDownloaded">
				{{ t('recognize', 'The machine learning models still need to be downloaded.') }}
			</NcNoteCard>
			<template v-if="settings['faces.enabled'] || settings['imagenet.enabled'] || settings['musicnn.enabled'] || settings['movinet.enabled']">
				<NcNoteCard show-alert type="success">
					{{ t('recognize', 'The app is installed and will automatically classify files in background processes.') }}
				</NcNoteCard>
			</template>
			<p v-else>
				{{ t('recognize', 'None of the tagging options below are currently selected. The app will currently do nothing.') }}
			</p>
		</NcSettingsSection>
		<NcSettingsSection :title="t('recognize', 'Face recognition')">
			<p>
				<NcCheckboxRadioSwitch :checked.sync="settings['faces.enabled']"
					type="switch"
					@update:checked="onChange('faces.enabled', $event)">
					{{ t('recognize', 'Enable face recognition (groups photos by faces that appear in them; UI is in the photos app)') }}
				</NcCheckboxRadioSwitch>
			</p>
		</NcSettingsSection>
		<NcSettingsSection :title="t('recognize', 'Object detection & landmark recognition')">
			<p>
				<NcCheckboxRadioSwitch :checked.sync="settings['imagenet.enabled']"
					type="switch"
					@update:checked="onChange('imagenet.enabled', $event)">
					{{ t('recognize', 'Enable object recognition (e.g. food, vehicles, landscapes)') }}
				</NcCheckboxRadioSwitch>
			</p>
			<p>&nbsp;</p>
			<p>
				<NcCheckboxRadioSwitch :checked.sync="settings['landmarks.enabled']"
					type="switch"
					:disabled="!settings['imagenet.enabled']"
					@update:checked="onChange('landmarks.enabled', $event)">
					{{ t('recognize', 'Enable landmark recognition (e.g. Eiffel Tower, Golden Gate Bridge)') }}
				</NcCheckboxRadioSwitch>
			</p>
		</NcSettingsSection>
		<NcSettingsSection :title="t('recognize', 'Audio tagging')">
			<p>
				<NcCheckboxRadioSwitch :checked.sync="settings['musicnn.enabled']"
					type="switch"
					@update:checked="onChange('musicnn.enabled', $event)">
					{{ t('recognize', 'Enable music genre recognition (e.g. pop, rock, folk, metal, new age)') }}
				</NcCheckboxRadioSwitch>
			</p>
		</NcSettingsSection>
		<NcSettingsSection :title="t('recognize', 'Video tagging')">
			<p>
				<NcCheckboxRadioSwitch :checked.sync="settings['movinet.enabled']"
					type="switch"
					:disabled="platform !== 'x86_64' || tensorflowPurejs"
					@update:checked="onChange('movinet.enabled', $event)">
					{{ t('recognize', 'Enable human action recognition (e.g. arm wrestling, dribbling basketball, hula hooping)') }}
				</NcCheckboxRadioSwitch>
			</p>
		</NcSettingsSection>
	</div>
</template>

<script>
import { NcNoteCard, NcSettingsSection, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'

/* every setting is a boolean setting */
const SETTINGS = ['geo.enabled', 'imagenet.enabled', 'landmarks.enabled', 'faces.enabled', 'musicnn.enabled', 'movinet.enabled']

export default {
	name: 'ViewUser',
	components: { NcSettingsSection, NcNoteCard, NcCheckboxRadioSwitch },

	data() {
		return {
			loading: false,
			successTick: false,
			error: '',
			settings: SETTINGS.reduce((obj, key) => ({ ...obj, [key]: false }), {}),
			platform: undefined,
			tensorflowPurejs: undefined,
			modelsDownloaded: null,
		}
	},

	watch: {
		error(error) {
			if (!error) return
			OC.Notification.showTemporary(error)
		},
	},

	async created() {
		this.modelsDownloaded = loadState('recognize', 'modelsDownloaded')
		this.getPlatform()
		this.getTensorflowPurejs()

		try {
			const settings = loadState('recognize', 'user_settings')
			for (const setting of SETTINGS) {
				this.settings[setting] = JSON.parse(settings[setting]) ?? false
			}
		} catch (e) {
			this.error = this.t('recognize', 'Failed to load settings')
			throw e
		}
	},

	methods: {
		async getPlatform() {
			const resp = await axios.get(generateUrl('/apps/recognize/user/platform'))
			const { platform } = resp.data
			this.platform = platform
		},
		async getTensorflowPurejs() {
			const resp = await axios.get(generateUrl('/apps/recognize/user/tensorflow_purejs'))
			const { tensorflowPurejs } = resp.data
			this.tensorflowPurejs = tensorflowPurejs
		},

		async onChange(setting, value = false) {
			this.loading = true
			await this.setValue(setting, value)
			this.loading = false
		},

		async setValue(setting, value) {
			try {
				await axios.put(generateUrl(`/apps/recognize/user/settings/${setting}`), {
					value: JSON.stringify(value),
				})
				this.successTick = true
				setTimeout(() => (this.successTick = false), 3000)
			} catch (e) {
				this.error = this.t('recognize', 'Failed to save settings')
				this.successTick = false
			}
		},
	},
}
</script>

<style scoped>
figure[class^='icon-'] {
	display: inline-block;
}

#recognize {
	position: relative;
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

<!--
  - Copyright (c) 2021. The Recognize contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div id="recognize">
		<SettingsSection :title="t('recognize', 'Face recognition')">
			<p>These people have been spotted by Recognize in your photos. You can give each person a name.</p>
			<FaceCluster v-for="cluster in faceClusters" :key="cluster.id" :cluster="cluster" />
		</SettingsSection>
	</div>
</template>

<script>
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import { loadState } from '@nextcloud/initial-state'
import FaceCluster from './FaceCluster'

export default {
	name: 'ViewUser',
	components: { FaceCluster, SettingsSection },
	data() {
		return {
			faceClusters: loadState('recognize', 'faceClusters'),
		}
	},
}
</script>
<style>
</style>

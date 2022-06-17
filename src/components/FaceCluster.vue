<template>
	<div class="face-cluster">
		<h3 style="display: flex; flex-direction: row">
			<template v-if="!editing">
				<span style="padding: 10px 0"><strong>Person</strong> {{ cluster.title || 'Untitled' }}</span><Actions>
					<ActionButton icon="icon-rename" @click="editing = true">
						Edit
					</ActionButton>
				</Actions>
			</template>
			<template v-else>
				<span style="padding: 10px 0"><strong>Person</strong> <input v-model="cluster.title"
					v-focus
					type="text"
					@blur="editingDone"
					@keydown.enter.stop.prevent="editingDone"></span>
			</template>
		</h3>
		<div style="display: flex; flex-direction: row; overflow-x: scroll; overflow-y: hidden; width: 80vw;">
			<FaceDetection v-for="detection in cluster.detections"
				:key="detection.id"
				:file-id="detection.fileId"
				:x="detection.x"
				:y="detection.y"
				:width="detection.width"
				:height="detection.height" />
		</div>
	</div>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import FaceDetection from './FaceDetection.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
export default {
	name: 'FaceCluster',
	components: { FaceDetection, Actions, ActionButton },
	directives: {
		focus(el) {
			setTimeout(() => {
				el.focus()
			}, 100)
		},
	},
	props: {
		cluster: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			editing: false,
			error: null,
		}
	},
	watch: {
		error(error) {
			if (!error) return
			OC.Notification.showTemporary(error)
		},
	},
	methods: {
		async editingDone() {
			this.editing = false
			try {
				await axios.post(generateUrl(`/apps/recognize/user/cluster/${this.cluster.id}`), { title: this.cluster.title })
			} catch (e) {
				this.error = 'Failed to save name'
			}
		},
	},
}
</script>

<style scoped>
.face-cluster {
	margin-bottom: 20px;
}
</style>

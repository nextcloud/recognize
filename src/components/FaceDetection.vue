<template>
	<div class="face-detection">
		<img ref="image"
			:src="src"
			loading="lazy"
			@load="onLoaded">
		<canvas ref="canvas" height="256" width="256" />
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'FaceDetection',
	props: {
		fileId: {
			type: Number,
			required: true,
		},
		x: {
			type: Number,
			required: true,
		},
		y: {
			type: Number,
			required: true,
		},
		height: {
			type: Number,
			required: true,
		},
		width: {
			type: Number,
			required: true,
		},
	},
	computed: {
		src() {
			return generateUrl(`/core/preview?fileId=${this.fileId}&x=256&y=256&a=1`)
		},
	},
	methods: {
		onLoaded() {
			const canvas = this.$refs.canvas
			const ctx = canvas.getContext('2d')

			ctx.strokeStyle = this.colorPrimaryElementLight
			ctx.lineWidth = 4
			const width = this.$refs.image.naturalWidth
			const height = this.$refs.image.naturalHeight
			ctx.strokeRect(width * this.x - 2, height * this.y - 2, width * this.width + 2, height * this.height + 2)
		},
	},
}
</script>

<style scoped>
.face-detection {
	height: 256px;
	width: 256px;
	position: relative;
	margin-right: 8px;
}
canvas {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	width: 256px;
	height: 256px;
}
</style>

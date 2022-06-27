<template>
	<div class="face-detection">
		<img ref="image"
			:src="`/index.php/core/preview?fileId=${fileId}&x=256&y=256&a=true`"
			loading="lazy"
			@load="onLoaded">
		<canvas ref="canvas" />
	</div>
</template>

<script>
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
}
</style>

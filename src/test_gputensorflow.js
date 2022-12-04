const tf = require('@tensorflow/tfjs-node-gpu')

tf.setBackend('tensorflow')
	.then(() => { process.exit(tf.backend().isGPUPackage && tf.backend().isUsingGpuDevice ? 0 : 1) })
	.catch(e => {
		console.error(e)
		process.exit(1)
	})

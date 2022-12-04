const tf = require('@tensorflow/tfjs-node')

tf.setBackend('tensorflow')
	.then(() => process.exit(0))
	.catch(e => {
		console.error(e)
		process.exit(1)
	})

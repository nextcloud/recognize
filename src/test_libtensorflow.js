let tf
if (process.env.RECOGNIZE_GPU === 'true') {
	tf = require('@tensorflow/tfjs-node-gpu')
} else {
	tf = require('@tensorflow/tfjs-node')
}

tf.setBackend('tensorflow')
	.then(() => process.exit(0))
	.catch(e => {
		console.error(e)
		process.exit(1)
	})

const tf = require('@tensorflow/tfjs')
require('@tensorflow/tfjs-backend-wasm')

tf.setBackend('wasm')
	.then(() => process.exit(0))
	.catch(e => {
		console.error(e)
		process.exit(1)
	})

const path = require('path')
const _ = require('lodash')
const fs = require('fs/promises')

let tf, faceapi, Jimp
let PUREJS = false
if (process.env.RECOGNIZE_PUREJS === 'true') {
	tf = require('@tensorflow/tfjs')
	require('@tensorflow/tfjs-backend-wasm')
	faceapi = require('@vladmandic/face-api/dist/face-api.node-wasm.js')
	Jimp = require('jimp')
	PUREJS = true
} else {
	try {
		if (false && process.env.RECOGNIZE_GPU === 'true') {
			tf = require('@tensorflow/tfjs-node-gpu')
			faceapi = require('@vladmandic/face-api/dist/face-api.node-gpu.js')
		} else {
			tf = require('@tensorflow/tfjs-node')
			faceapi = require('@vladmandic/face-api/dist/face-api.node.js')
		}
	} catch (e) {
		console.error(e)
		console.error('Trying js-only mode')
		tf = require('@tensorflow/tfjs')
		require('@tensorflow/tfjs-backend-wasm')
		faceapi = require('@vladmandic/face-api/dist/face-api.node-wasm.js')
		Jimp = require('jimp')
		PUREJS = true
	}
}

if (process.argv.length < 3) throw new Error('Incorrect arguments: node classifier_faces.js ...<IMAGE_FILES> | node classify.js -')

/**
 *
 */
async function main() {
	const getStdin = (await import('get-stdin')).default
	let paths, facesDefinitionJSON
	if (process.argv[2] === '-') {
		paths = (await getStdin()).split('\n')
	} else {
		paths = process.argv.slice(2)
	}

	await faceapi.nets.ssdMobilenetv1.loadFromDisk(path.resolve(__dirname, '..', 'node_modules/@vladmandic/face-api/model'))
	await faceapi.nets.faceLandmark68Net.loadFromDisk(path.resolve(__dirname, '..', 'node_modules/@vladmandic/face-api/model'))
	await faceapi.nets.faceRecognitionNet.loadFromDisk(path.resolve(__dirname, '..', 'node_modules/@vladmandic/face-api/model'))

	for (const path of paths) {
		try {
			let tensor
			if (PUREJS) {
				tensor = await createTensor(await Jimp.read(path), 3)
			} else {
				tensor = await tf.node.decodeImage(await fs.readFile(path), 3)
			}
			const results = await faceapi.detectAllFaces(tensor).withFaceLandmarks().withFaceDescriptors()
			tensor.dispose()

			const vectors = results
				.map(result => ({
					vector: result.descriptor,
					x: result.detection.relativeBox.x,
					y: result.detection.relativeBox.y,
					height: result.detection.relativeBox.height,
					width: result.detection.relativeBox.width,
					score: result.detection.score,
				}))

			console.log(JSON.stringify(vectors))
		} catch (e) {
			console.error(e)
			console.log('[]')
		}
	}
}

tf.setBackend(process.env.RECOGNIZE_PUREJS === 'true' ? 'wasm' : 'tensorflow')
	.then(() => main())
	.catch(e => {
		console.error(e)
	})

/**
 * @param image
 */
async function createTensor(image) {
	const NUM_OF_CHANNELS = 3
	const values = new Float32Array(image.bitmap.width * image.bitmap.height * NUM_OF_CHANNELS)
	let i = 0
	image.scan(0, 0, image.bitmap.width, image.bitmap.height, (x, y) => {
		const pixel = Jimp.intToRGBA(image.getPixelColor(x, y))
		values[i * NUM_OF_CHANNELS + 0] = pixel.r
		values[i * NUM_OF_CHANNELS + 1] = pixel.g
		values[i * NUM_OF_CHANNELS + 2] = pixel.b
		i++
	})
	const outShape = [
		image.bitmap.height,
		image.bitmap.width,
		NUM_OF_CHANNELS,
	]
	const imageTensor = tf.tensor3d(values, outShape, 'float32')
	return imageTensor
}

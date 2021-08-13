const path = require('path')
const fsSync = require('fs')
const _ = require('lodash')
const Jimp = require('jimp')
const tf = require('@tensorflow/tfjs-node-gpu')
require('@tensorflow/tfjs-backend-wasm')
const faceapi = require('@vladmandic/face-api/dist/face-api.node-gpu.js')

if (process.argv.length < 3) throw new Error('Incorrect arguments: node classifier_faces.js ...<IMAGE_FILES> | node classify.js -')

let paths, facesDefinitionJSON
if (process.argv[2] === '-') {
	const lines = fsSync.readFileSync(process.stdin.fd).toString('utf8').split('\n')
	facesDefinitionJSON = lines.slice(0, lines.indexOf('')).join('\n')
	paths = lines.slice(lines.indexOf('') + 1)
} else {
	facesDefinitionJSON = fsSync.readFileSync(process.stdin.fd).toString('utf8')
	paths = process.argv.slice(2)
}

const facesDefinition = JSON.parse(facesDefinitionJSON)

async function main() {
	await faceapi.nets.ssdMobilenetv1.loadFromDisk(path.resolve(__dirname, '..', 'node_modules/@vladmandic/face-api/model'))
	await faceapi.nets.faceLandmark68Net.loadFromDisk(path.resolve(__dirname, '..', 'node_modules/@vladmandic/face-api/model'))
	await faceapi.nets.faceRecognitionNet.loadFromDisk(path.resolve(__dirname, '..', 'node_modules/@vladmandic/face-api/model'))

	const faceDescriptors = {}
	for (const person in facesDefinition) {
		const tensor = await tf.node.decodeImage(fsSync.readFileSync(facesDefinition[person]), 3)
		const result = await faceapi.detectSingleFace(tensor).withFaceLandmarks().withFaceDescriptor()
		if (!result) {
			continue
		}
		faceDescriptors[person] = new faceapi.LabeledFaceDescriptors(person, [result.descriptor])
	}

	if (!Object.values(faceDescriptors).length) {
		return
	}

	const faceMatcher = new faceapi.FaceMatcher(Object.values(faceDescriptors))

	for (const path of paths) {
		try {
			const tensor = await tf.node.decodeImage(fsSync.readFileSync(path), 3)
			const results = await faceapi.detectAllFaces(tensor).withFaceLandmarks().withFaceDescriptors()
			const labels = results
				.map(result => faceMatcher.findBestMatch(result.descriptor).label)
				.filter(label => label !== 'unknown')

			console.log(JSON.stringify(_.uniq(labels)))
		} catch (e) {
			console.error(e)
			console.log('[]')
		}
	}
}

tf.setBackend('tensorflow')
	.then(() => main())
	.catch(e => {
		console.error(e)
		tf.setBackend('wasm')
			.then(() => main())
	})

async function createTensor(image) {
	const NUM_OF_CHANNELS = 3
	const values = new Float32Array(image.bitmap.width * image.bitmap.height * NUM_OF_CHANNELS)
	let i = 0
	image.scan(0, 0, image.bitmap.width, image.bitmap.height, (x, y) => {
		const pixel = Jimp.intToRGBA(image.getPixelColor(x, y))
		pixel.r = ((pixel.r - 1) / 127.0) >> 0
		pixel.g = ((pixel.g - 1) / 127.0) >> 0
		pixel.b = ((pixel.b - 1) / 127.0) >> 0
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
	// imageTensor = imageTensor.expandDims(0)
	return imageTensor
}

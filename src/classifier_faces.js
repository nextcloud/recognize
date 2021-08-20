const path = require('path')
const fs = require('fs/promises')
const _ = require('lodash')
const tf = require('@tensorflow/tfjs-node-gpu')
require('@tensorflow/tfjs-backend-wasm')
const faceapi = require('@vladmandic/face-api/dist/face-api.node-gpu.js')

if (process.argv.length < 3) throw new Error('Incorrect arguments: node classifier_faces.js ...<IMAGE_FILES> | node classify.js -')

async function main() {
	let paths, facesDefinitionJSON
	if (process.argv[2] === '-') {
		const lines = (await fs.readFile('/dev/stdin')).toString('utf8').split('\n')
		facesDefinitionJSON = lines.slice(0, lines.indexOf('')).join('\n')
		paths = lines.slice(lines.indexOf('') + 1)
	} else {
		facesDefinitionJSON = (await fs.readFile('/dev/stdin')).toString('utf8')
		paths = process.argv.slice(2)
	}

	const facesDefinition = JSON.parse(facesDefinitionJSON)
	console.error(facesDefinition)

	await faceapi.nets.ssdMobilenetv1.loadFromDisk(path.resolve(__dirname, '..', 'node_modules/@vladmandic/face-api/model'))
	await faceapi.nets.faceLandmark68Net.loadFromDisk(path.resolve(__dirname, '..', 'node_modules/@vladmandic/face-api/model'))
	await faceapi.nets.faceRecognitionNet.loadFromDisk(path.resolve(__dirname, '..', 'node_modules/@vladmandic/face-api/model'))

	const faceDescriptors = {}
	for (const person in facesDefinition) {
		try {
			const tensor = await tf.node.decodeImage(await fs.readFile(facesDefinition[person]), 3)
			const result = await faceapi.detectSingleFace(tensor).withFaceLandmarks().withFaceDescriptor()
			tensor.dispose()
			if (!result) {
				continue
			}
			faceDescriptors[person] = new faceapi.LabeledFaceDescriptors(person, [result.descriptor])
		} catch (e) {
			console.error(e)
		}
	}

	if (!Object.values(faceDescriptors).length) {
		return
	}

	const faceMatcher = new faceapi.FaceMatcher(Object.values(faceDescriptors), 0.4) // default is 0.6

	for (const path of paths) {
		try {
			const tensor = await tf.node.decodeImage(await fs.readFile(path), 3)
			const results = await faceapi.detectAllFaces(tensor).withFaceLandmarks().withFaceDescriptors()
			tensor.dispose()
			const labels = results
				.map(result => faceMatcher.findBestMatch(result.descriptor).label)
				.filter(label => label !== 'unknown')

			if (results.length) {
				labels.push('people')
			}

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

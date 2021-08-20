let tf
if (process.env.RECOGNIZE_GPU === 'true') {
	tf = require('@tensorflow/tfjs-node-gpu')
} else {
	tf = require('@tensorflow/tfjs-node')
}

const { IMAGENET_CLASSES } = require('./classes')
const fs = require('fs/promises')
const NUM_OF_CHANNELS = 3
class EfficientNetModel {

	constructor(modelPath, imageSize) {
		this.modelPath = modelPath
		this.imageSize = imageSize
	}

	static async create(localModelRootDirectory) {
		const modelFileName = 'model.json'
		const modelPath = `file://${localModelRootDirectory}/${modelFileName}`
		const model = new EfficientNetModel(modelPath, 512)
		await model.load()
		return model
	}

	async load() {
		this.model = await tf.loadGraphModel(this.modelPath)
	}

	predict(tensor, topK) {
		return this.model.predict(tensor)
	}

	async inference(imgPath, options) {
		const { topK = NUM_OF_CHANNELS } = options || {}
		const inputMax = 1
		const inputMin = -1
		const normalizationConstant = (inputMax - inputMin) / 255.0
		const image = await tf.node.decodeImage(await fs.readFile(imgPath), 3)

		const logits = tf.tidy(() => {
			// Normalize the image from [0, 255] to [inputMin, inputMax].
			const normalized = tf.add(
				tf.mul(tf.cast(image, 'float32'), normalizationConstant),
				inputMin)

			// Resize the image to
			let resized = normalized
			if (image.shape[0] !== this.imageSize || image.shape[1] !== this.imageSize) {
				const alignCorners = true
				resized = tf.image.resizeBilinear(
					normalized, [this.imageSize, this.imageSize], alignCorners)
			}

			// Reshape so we can pass it to predict.
			const reshaped = tf.reshape(resized, [-1, this.imageSize, this.imageSize, 3])

			return this.predict(reshaped, topK)
		})
		const values = await tf.softmax(logits)
		const prediction = getTopKClasses(await values.data(), topK)
		logits.dispose()
		values.dispose()
		image.dispose()
		return prediction
	}

}

function getTopKClasses(values, topK) {
	const valuesAndIndices = []
	for (let i = 0; i < values.length; i++) {
		valuesAndIndices.push({ value: values[i], index: i })
	}
	valuesAndIndices.sort((a, b) => {
		return b.value - a.value
	})
	const topkValues = new Float32Array(topK)
	const topkIndices = new Int32Array(topK)
	for (let i = 0; i < topK; i++) {
		topkValues[i] = valuesAndIndices[i].value
		topkIndices[i] = valuesAndIndices[i].index
	}
	const topClassesAndProbs = []
	for (let i = 0; i < topkIndices.length; i++) {
		topClassesAndProbs.push({
			className: IMAGENET_CLASSES[topkIndices[i]],
			probability: topkValues[i],
		})
	}
	return topClassesAndProbs
}

module.exports = EfficientNetModel

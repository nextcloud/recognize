let tf, Jimp
let PUREJS = false
if (process.env.RECOGNIZE_PUREJS === 'true') {
	tf = require('@tensorflow/tfjs')
	PUREJS = true
	Jimp = require('jimp')
} else {
	try {
		tf = require('@tensorflow/tfjs-node')
	} catch (e) {
		console.error(e)
		console.error('Trying js-only mode')
		tf = require('@tensorflow/tfjs')
		PUREJS = true
		Jimp = require('jimp')
	}
}

const { IMAGENET_CLASSES } = require('./classes')
const fs = require('fs/promises')
const NUM_OF_CHANNELS = 3
class EfficientNetModel {

	constructor(modelPath, imageSize) {
		this.modelPath = modelPath
		this.imageSize = imageSize
	}

	static async create(modelURL) {
		const model = new EfficientNetModel(modelURL, 512)
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
		let image
		if (PUREJS) {
			const jimage = await Jimp.read(imgPath)
			image = await this.createTensor(jimage)
		} else {
			image = await tf.node.decodeImage(await fs.readFile(imgPath), 3)
		}

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

	async createTensor(image) {
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

}

/**
 * @param values
 * @param topK
 */
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

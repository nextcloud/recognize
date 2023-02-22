let tf, Jimp
let PUREJS = false
if (process.env.RECOGNIZE_PUREJS === 'true') {
	tf = require('@tensorflow/tfjs')
	Jimp = require('jimp')
	PUREJS = true
} else {
	try {
		if (process.env.RECOGNIZE_GPU === 'true') {
			tf = require('@tensorflow/tfjs-node-gpu')
		} else {
			tf = require('@tensorflow/tfjs-node')
		}
	} catch (e) {
		console.error(e)
		console.error('Trying js-only mode')
		tf = require('@tensorflow/tfjs')
		Jimp = require('jimp')
		PUREJS = true
	}
}

const { KINETIC_600_CLASSES } = require('./classes.js')
const ffmpeg = require('ffmpeg-static')
const execa = require('execa')
const NUM_OF_CHANNELS = 3
const FRAME_SIZE = 176

class MovinetModel {

	constructor(modelPath) {
		this.modelPath = modelPath
	}

	static async create(modelURL) {
		const model = new MovinetModel(modelURL)
		await model.load()
		return model
	}

	async load() {
		this.model = await tf.node.loadSavedModel(this.modelPath)
	}

	predict(tensor, topK) {
		return this.model.predict(tensor)
	}

	async inference(videoPath, options) {
		const { topK = 10 } = options || {}

		const frames = []

		console.error('Starting transcoding')
		const proc = execa(ffmpeg, [
			'-t', '30', // read 120s max
			'-i', videoPath,
			'-s', `${FRAME_SIZE}x${FRAME_SIZE}`,
			'-vf', 'fps=2',
			'-c:v', 'mjpeg',
			'-f', 'image2pipe',
			...(process.env.RECOGNIZE_CORES
				? ['-threads', process.env.RECOGNIZE_CORES]
				: []),
			'-',
		], { encoding: null, stripFinalNewline: false })

		proc.stderr.pipe(process.stderr)

		proc.stdout.on('data', buffer => {
			frames.push(buffer)
		})

		await proc

		console.error('finished transcoding')

		const frameTensors = []
		let i = 0
		for (const frame of frames) {
			let image
			if (PUREJS) {
				const jimage = await Jimp.read(frame)
				image = await this.createTensor(jimage)
			} else {
				image = await tf.node.decodeImage(frame, 3)
			}
			frameTensors.push(image)
			console.error('decoded ' + (++i) + '/' + frames.length + ' images')
		}

		const values = tf.tidy(() => {
			const frameTensor = tf.stack(frameTensors)
			const frameBatch = tf.expandDims(frameTensor.div(255), 0)
			const logits = this.predict(tf.cast(frameBatch, 'float32'), topK)
			return tf.softmax(logits)
		})

		const prediction = getTopKClasses(await values.data(), topK)
		values.dispose()
		frameTensors.map(t => t.dispose())
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
			className: KINETIC_600_CLASSES[topkIndices[i]],
			probability: topkValues[i],
		})
	}
	return topClassesAndProbs
}

module.exports = MovinetModel

let tf
let PUREJS = false
if (process.env.RECOGNIZE_PUREJS === 'true') {
	tf = require('@tensorflow/tfjs')
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
		PUREJS = true
	}
}

const { MSD_CLASSES } = require('./classes')
const ffmpeg = require('ffmpeg-static')
const execa = require('execa')
const WavDecoder = require('wav-decoder')
const MEL_MATRIX = require('./mel_matrix')
const fs = require('fs/promises')
const NUM_OF_CHANNELS = 3

class MusicnnModel {

	constructor(modelPath) {
		this.modelPath = modelPath
	}

	static async create(modelURL) {
		const model = new MusicnnModel(modelURL)
		await model.load()
		return model
	}

	async load() {
		this.model = await tf.node.loadSavedModel(this.modelPath)
	}

	predict(tensor, topK) {
		return this.model.predict(tensor)
	}

	async inference(songPath, options) {
		const { topK = 10 } = options || {}

		const { stdout } = await execa(ffmpeg, [
			'-i', songPath,
			'-f', 'wav',
			'-ac', '1',
			'-ar', '8000',
			'-acodec', 'pcm_s16le',
			'-',
		], { encoding: null, stripFinalNewline: false })

		const audioData = await WavDecoder.decode(stdout)

		const values = tf.tidy(() => {
			const melMatrix = tf.cast(MEL_MATRIX, 'float32')
			const song = tf.cast(audioData.channelData[0]/* .slice(0, 960000) */, 'float32')
			const spectrogram = tf.abs(tf.signal.stft(song, 512, 256, 512))
			let melSpectrogram = tf.log(tf.clipByValue(tf.matMul(spectrogram, melMatrix), 0.000001, Number.MAX_SAFE_INTEGER))
			melSpectrogram = tf.expandDims(melSpectrogram, -1)
			melSpectrogram = tf.slice(melSpectrogram, [Math.min(188 * 30, melSpectrogram.shape[0] - Math.floor(melSpectrogram.shape[0] / 188) * 188)])
			let spectrogramBatches = tf.split(melSpectrogram, Array(melSpectrogram.shape[0] / 188).fill(188), 0)
			const frames = melSpectrogram.shape[0] / 188
			spectrogramBatches = tf.stack(spectrogramBatches)

			const logits = this.predict(spectrogramBatches, topK)
			const logitBatches = tf.split(logits, frames, 0)
			const probsBatches = logitBatches.map(logits => tf.softmax(logits))
			const probabilities = tf.stack(probsBatches)
			return tf.mean(probabilities, 0)
		})

		const prediction = getTopKClasses(await values.data(), topK)
		values.dispose()
		return prediction
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
			className: MSD_CLASSES[topkIndices[i]],
			probability: topkValues[i],
		})
	}
	return topClassesAndProbs
}

module.exports = MusicnnModel

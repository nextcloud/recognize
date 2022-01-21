const path = require('path')
const VERSION = require('../package.json').version
const download = require('download')
const tar = require('tar')
const fsSync = require('fs')
const YAML = require('yaml')
const _ = require('lodash')
const LABELS = {
	landmarks_africa: require('./landmarks/africa.json').name,
	landmarks_asia: require('./landmarks/asia.json').name,
	landmarks_europe: require('./landmarks/europe.json').name,
	landmarks_north_america: require('./landmarks/north_america.json').name,
	landmarks_south_america: require('./landmarks/south_america.json').name,
	landmarks_oceania: require('./landmarks/oceania.json').name,
}

let tf, getPort, StaticServer
let PUREJS = false
if (process.env.RECOGNIZE_PUREJS === 'true') {
	tf = require('@tensorflow/tfjs')
	require('@tensorflow/tfjs-backend-wasm')
	getPort = require('get-port')
	StaticServer = require('static-server')
	PUREJS = true
} else {
	try {
		if (false && process.env.RECOGNIZE_GPU === 'true') {
			tf = require('@tensorflow/tfjs-node-gpu')
		} else {
			tf = require('@tensorflow/tfjs-node')
		}
	} catch (e) {
		console.error(e)
		console.error('Trying js-only mode')
		tf = require('@tensorflow/tfjs')
		require('@tensorflow/tfjs-backend-wasm')
		PUREJS = true
	}
}

const EfficientNet = require('./efficientnet/EfficientnetModel')
const THRESHOLD = 0.9

/**
 * @param className
 */
function findRule(className) {
	const rule = rules[className]
	if (!rule) {
		return
	}

	if (rule.see) {
		return findRule(rule.see)
	}

	return rule
}

if (process.argv.length < 3) throw new Error('Incorrect arguments: node classify.js ...<IMAGE_FILES> | node classify.js -')

/**
 * @param modelName
 * @param imgSize
 * @param minInput
 * @param paths
 */
async function main(modelName, imgSize, minInput, paths) {
	const modelPath = path.resolve(__dirname, '..', 'models', modelName)

	const modelFileName = 'model.json'
	let modelUrl
	if (PUREJS) {
		// See https://github.com/tensorflow/tfjs/issues/4927
		const port = await getPort()
		const server = new StaticServer({
			rootPath: modelPath,
			port,
		})

		await new Promise(resolve => server.start(resolve))

		modelUrl = `http://localhost:${port}/${modelFileName}`
	} else {
		modelUrl = `file://${modelPath}/${modelFileName}`
	}

	// Download model on first run
	if (!fsSync.existsSync(modelPath)) {
		await download(
			`https://github.com/marcelklehr/recognize/archive/refs/tags/v${VERSION}.tar.gz`,
			path.resolve(__dirname, '..')
		)
		await new Promise(resolve =>
			tar.x({
				strip: 1,
				C: path.resolve(__dirname, '..'),
				file: path.resolve(__dirname, '..', `recognize-${VERSION}.tar.gz`),
			}, [`recognize-${VERSION}/models/${modelName}`], resolve)
		)
	}

	const model = await EfficientNet.create(modelUrl, imgSize, minInput, LABELS[modelName])

	const result = []
	for (const path of paths) {
		try {
			let results = await model.inference(path, {
				topK: 7,
				softmax: false,
			})

			results = results
				.filter(result => {
					if (result.probability < 0.0) {
						return false
					}
					return result.probability >= THRESHOLD
				})

			result.push(results)
		} catch (e) {
			console.error(e)
			result.push([])
		}
	}
	return result
}

tf.setBackend(process.env.RECOGNIZE_PUREJS === 'true' ? 'wasm' : 'tensorflow')
	.then(async () => {
		const imgSize = 321
		const minInput = 0
		const models = ['landmarks_africa', 'landmarks_asia', 'landmarks_europe', 'landmarks_north_america', 'landmarks_south_america', 'landmarks_oceania']
		const getStdin = (await import('get-stdin')).default

		const paths = process.argv[2] === '-'
			? (await getStdin()).split('\n')
			: process.argv.slice(2)
		let error
		const labels = []
		for (const modelName of models) {
			try {
				const results = await main(modelName, imgSize, minInput, paths)
				results.forEach((result, i) => (labels[i] = labels[i] || []).push(...result))
			} catch (e) {
				console.error(e)
				error = e
			}
		}
		labels.forEach((labels, i) => {
			console.error(paths[i])
			labels.sort((a, b) => a.probability - b.probability).reverse()
			console.error(labels)
			if (labels.length) {
				console.log(JSON.stringify([labels[0].className]))
			} else {
				console.log(JSON.stringify([]))
			}
		})
		if (error) {
			throw error
		}
	})
	.then(() => process.exit(0))
	.catch(e => {
		console.error(e)
		process.exit(1)
	})

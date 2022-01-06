const path = require('path')
const VERSION = require('../package.json').version
const download = require('download')
const tar = require('tar')
const fsSync = require('fs')
const YAML = require('yaml')
const _ = require('lodash')
const rules = YAML.parse(fsSync.readFileSync(__dirname + '/musicnn_rules.yml').toString('utf8'))

let tf, getPort, StaticServer
let PUREJS = false
if (process.env.RECOGNIZE_PUREJS === 'true') {
	tf = require('@tensorflow/tfjs')
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
		PUREJS = true
	}
}

const Musicnn = require('./musicnn/MusicnnModel')

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
 *
 */
async function main() {
	const modelPath = path.resolve(__dirname, '..', 'music_model')

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
			}, [`recognize-${VERSION}/models/musicnn`], resolve)
		)
	}

	const model = await Musicnn.create(modelUrl)
	const getStdin = (await import('get-stdin')).default

	const paths = process.argv[2] === '-'
		? (await getStdin()).split('\n')
		: process.argv.slice(2)

	for (const path of paths) {
		try {
			let results = await model.inference(path, {
				topK: 6,
			})

			const labels = []
			results = results
				.map(result => ({
					...result,
					probability: result.probability,
					className: result.className ? result.className.split(',')[0].toLowerCase() : result.className,
				}))
				.map(result => ({
					...result,
					rule: findRule(result.className),
				}))

			results
				.filter(result => {
					console.error(result)
					if (result.probability < 0.0 || !result.rule) {
						return false
					}
					const threshold = result.rule.threshold
					return result.probability >= threshold
				})
				.forEach((result) => {
					if (result.rule.label) {
						labels.push(result.rule.label)
					}
					if (result.rule.categories) {
						labels.push(...result.rule.categories)
					}
				})
			const cat_probabilities = {}
			const cat_thresholds = {}
			const cat_count = {}
			results.forEach(result => {
				if (result.rule) {
					let categories = []
					if (result.rule.label) {
						categories.push(result.rule.label)
					}
					if (result.rule.categories) {
						categories = categories.concat(result.rule.categories)
					}
					_.uniq(categories).forEach(category => {
						if (!(category in cat_probabilities)) {
							cat_probabilities[category] = 0
						}
						if (!(category in cat_thresholds)) {
							cat_thresholds[category] = 0
						}
						if (!(category in cat_count)) {
							cat_count[category] = 0
						}
						cat_probabilities[category] += result.probability ** 2
						cat_thresholds[category] = Math.max(cat_thresholds[category], result.rule.threshold)
						cat_count[category]++
					})
				}
			})
			Object.entries(cat_probabilities)
				.filter(([category, probability]) => {
					if (cat_count[category] <= 1) {
						return false
					}
					return probability ** (1 / 2) >= cat_thresholds[category]
				})
				.forEach(([category]) => {
					labels.push(category)
				})

			console.log(JSON.stringify(_.uniq(labels)))
		} catch (e) {
			console.error(e)
			console.log('[]')
		}
	}
}

tf.setBackend(process.env.RECOGNIZE_PUREJS === 'true' ? 'cpu' : 'tensorflow')
	.then(() => main())
	.then(() => process.exit(0))
	.catch(e => {
		console.error(e)
		process.exit(1)
	})

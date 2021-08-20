const path = require('path')
const VERSION = require('../package.json').version
const download = require('download')
const tar = require('tar')
const fsSync = require('fs')
const YAML = require('yaml')
const _ = require('lodash')
const rules = YAML.parse(fsSync.readFileSync(__dirname + '/rules.yml').toString('utf8'))

let tf
if (process.env.RECOGNIZE_GPU === 'true') {
	tf = require('@tensorflow/tfjs-node-gpu')
} else {
	tf = require('@tensorflow/tfjs-node')
}
require('@tensorflow/tfjs-backend-wasm')

const EfficientNet = require('./efficientnet/EfficientnetModel')

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

const paths = process.argv[2] === '-' ? fsSync.readFileSync(process.stdin.fd).toString('utf8').split('\n') : process.argv.slice(2)

async function main() {
	const modelPath = path.resolve(__dirname, '..', 'model')

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
				file: `recognize-${VERSION}.tar.gz`,
			}, [`recognize-${VERSION}/model`], resolve)
		)
	}

	const model = await EfficientNet.create(modelPath)

	for (const path of paths) {
		try {
			let results = await model.inference(path, {
				topK: 7,
			})

			const labels = []
			results = results
				.map(result => ({
					...result,
					probability: result.probability,
					className: result.className.split(',')[0].toLowerCase(),
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

tf.setBackend('tensorflow')
	.then(() => main())
	.catch(e =>
		tf.setBackend('wasm')
			.then(() => main())
	)

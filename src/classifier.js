const path = require('path')
const fsSync = require('fs')
const YAML = require('yaml')
const _ = require('lodash')
const tf = require('@tensorflow/tfjs-node')
require('@tensorflow/tfjs-backend-wasm')
const rules = YAML.parse(fsSync.readFileSync(__dirname + '/rules.yml').toString('utf8'))

const {
	EfficientNetCheckPointFactory,
	EfficientNetCheckPoint
} = require("./efficientnet");

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

if (process.argv.length < 3) throw new Error('Incorrect arguments: node classify.js ...<IMAGE_FILES>')

const paths = process.argv.slice(2)

async function main() {
	const model = await EfficientNetCheckPointFactory.create(
		EfficientNetCheckPoint.B3,
		{
			localModelRootDirectory: path.resolve(__dirname, '..', "model")
		}
	);

	for (const path of paths) {
		try {
			let results = await model.inference(path, {
				topK: 7
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
					if (result.probability < threshold) {
						return false
					}
					return true
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
							cat_thresholds[category] = 1
						}
						if (!(category in cat_count)) {
							cat_count[category] = 0
						}
						cat_probabilities[category] += result.probability
						cat_thresholds[category] += result.rule.threshold**2
						cat_count[category]++
					})
				}
			})
			Object.entries(cat_probabilities)
				.filter(([category, probability]) => {
					if (cat_count[category] <= 1) {
						return false
					}
					return probability >= (cat_thresholds[category]/cat_count[category])**(1/2)
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

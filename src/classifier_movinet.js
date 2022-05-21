const path = require('path')
const fsSync = require('fs')
const _ = require('lodash')

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

const Movinet = require('./movinet/MovinetModel')
const { downloadAll } = require('./model-manager')

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
	const modelPath = path.resolve(__dirname, '..', 'models', 'movinet-a3')

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
		modelUrl = `${modelPath}/`
	}

	// Download models on first run
	if (!fsSync.existsSync(modelPath)) {
		await downloadAll()
	}

	const model = await Movinet.create(modelUrl)
	const getStdin = (await import('get-stdin')).default

	const paths = process.argv[2] === '-'
		? (await getStdin()).split('\n')
		: process.argv.slice(2)

	for (const path of paths) {
		try {
			const results = await model.inference(path, {
				topK: 6,
			})

			const threshold = 0.85

			const labels = results
				.filter(result => {
					console.error(result)
					return result.probability >= threshold
				})
				.map(result => result.className)

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

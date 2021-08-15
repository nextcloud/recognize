const Flickr = require('flickr-sdk')
const download = require('download')
const fsSync = require('fs')
const YAML = require('yaml')
const flatten = require('lodash/flatten')
const uniq = require('lodash/uniq')
const execa = require('execa')
const glob = require('fast-glob')
const Parallel = require('async-parallel')

const rules = YAML.parse(fsSync.readFileSync(__dirname + '/../src/rules.yml').toString('utf8'))

const PHOTOS_PER_LABEL = 35
const PHOTOS_OLDER_THAN = 1627464319 // 2021-07-28; for determinism
const flickr = new Flickr(process.env.FLICKR_API_KEY)

const labels = uniq(flatten(Object.entries(rules)
	.map(([key, entry]) =>
		entry.label
			? [entry.label]
			: []
	)))

;(async function() {
	const results = await Parallel.map(labels, async label => {
		// First we calculate the true positive rate
		let tpr = 0
		let tnr = -1

		try {
			const matchingRules = Object.entries(rules)
				.map(([className, value]) => ({...value, className}))
				.filter((rule) => rule.label === label)

			await Promise.all(matchingRules.map(async rule => {
				if (rule.categories) {
					const urls = await findPhotos(rule.categories.join(' ') + ' ' + label+' '+rule.className)
					await Promise.all(
						urls.map(url => download(url, 'temp_images/' + label))
					)
				}else{
					const urls = await findPhotos(label+' '+rule.className)
					await Promise.all(
						urls.map(url => download(url, 'temp_images/' + label))
					)
				}
			}))

			const files = await glob(['temp_images/' + label + '/*'])
			if (!files.length) {
				throw new Error('No photos found for label "' + label + '"')
			}
			const { stdout } = await execa('node', [__dirname + '/../src/classifier.js'].concat(files))
			const predictions = stdout.split('\n')
				.map(line => JSON.parse(line))
			const matches = predictions
				.map((labels, i) => labels.includes(label) ? files[i] : labels)

			const matchCount = matches.filter((item, i) => files[i] === item).length
			tpr = matchCount / files.length

			if (matchingRules.some(rule => (rule.categories || rule.context))) {
			    // If we have rules or categories, we calculate false positive rate

				await Promise.all(matchingRules.map(async rule => {
					if (rule.context) {
						const urls = await findPhotos(rule.context + ' -' + label+ ' '+rule.className.split(' ').map(s => '-'+s).join(' '))
						await Promise.all(
							urls.map(url => download(url, 'temp_images/-' + label))
						)
					} else if (rule.categories) {
						const urls = await findPhotos(rule.categories.join(' ') + ' -' + label+ ' '+rule.className.split(' ').map(s => '-'+s).join(' '))
						await Promise.all(
							urls.map(url => download(url, 'temp_images/-' + label))
						)
					}
				}))


				const files = await glob(['temp_images/-' + label + '/*'])
				if (!files.length) {
					throw new Error('No photos found for label -"' + label + '"')
				}
				const { stdout } = await execa('node', [__dirname + '/../src/classifier.js'].concat(files))
				const predictions = stdout.split('\n')
					.map(line => JSON.parse(line))
				const matches = predictions
					.map((labels, i) => labels.includes(label) ? files[i] : labels)

				const matchCount = matches.filter((item, i) => files[i] === item).length
				tnr = 1 - (matchCount / (files.length))
			}
			console.log('Processed photos for label "' + label + '"')
		} catch (e) {
			console.log('Error processing photos for label "' + label + '"')
			console.log(e)
		}

		console.log({ tpr, tnr })

		return { tpr, tnr }
	}, 2)

	const sum = results.reduce((acc, val) => {
		return { tpr: acc.tpr + val.tpr, tnr: acc.tnr + (val.tnr > -1? val.tnr : 0) }
	}, { tpr: 0, tnr: 0 })

	const averageTPR = sum.tpr / results.length
	const averageTNR = sum.tnr / results.filter(val => val.tnr > -1).length
	const balancedAccuracy = (averageTPR + averageTNR) / 2

	console.log({ averageTPR, averageTNR, balancedAccuracy })

	const worstLabels = Object.fromEntries(
		results
			.map((result, i) => [labels[i], result])
			.filter(([, result]) => (result.tpr < 0.5 || result.tnr < 0.5 && result.tnr >= 0))
	)

	console.log({ worstLabels })

	if (balancedAccuracy < 0.5) {
		process.exit(1)
	}
})()

function findPhotos(label) {
	console.log('FLICKR search: '+label)
	return flickr.photos.search({
		text: label,
		per_page: PHOTOS_PER_LABEL,
		media: 'photos',
		content_type: 1,
		max_upload_date: PHOTOS_OLDER_THAN,
		sort: 'relevance',
	}).then(function(res) {
		if (res.body.stat === 'ok') {
			return res.body.photos.photo.map(photo => `https://live.staticflickr.com/${photo.server}/${photo.id}_${photo.secret}.jpg`)
		} else {
			console.log(res.body)
		}

	}).catch(function(err) {
		throw err
	})
}

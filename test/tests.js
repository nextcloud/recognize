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

const PHOTOS_PER_LABEL = 100
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
        let tpr

		try {
			const urls = await findPhotos(label)
			await Promise.all(
				urls.map(url => download(url, 'temp_images/' + label))
			)

			const files = await glob(['temp_images/' + label + '/*'])
			if (!files.length) {
				console.log('No photos found for label "' + label + '"')
				return 0
			}
			const { stdout } = await execa('node', [__dirname + '/../src/classifier.js'].concat(files))
			const predictions = stdout.split('\n')
				.map(line => JSON.parse(line))
			const matches = predictions
				.map((labels, i) => labels.includes(label) ? files[i] : labels)

			const matchCount = matches.filter((item, i) => files[i] === item).length
			tpr = matchCount / files.length

            console.log('Processed photos for label "' + label + '"')



        let tnr = 0

		if (rules[label].categories) {
			// If we have categories, we calculate false positive rate
				for (const category of rules[label].categories) {
					const urls = await findPhotos(category + ' -' + label)
					await Promise.all(
						urls.map(url => download(url, 'temp_images/-' + label))
					)
				}

				const files = await glob(['temp_images/-' + label + '/*'])
				if (!files.length) {
					console.log('No photos found for label -"' + label + '"')
					return 0
				}
				const { stdout } = await execa('node', [__dirname + '/../src/classifier.js'].concat(files))
				const predictions = stdout.split('\n')
					.map(line => JSON.parse(line))
				const matches = predictions
					.map((labels, i) => labels.includes(label) ? files[i] : labels)

				const matchCount = matches.filter((item, i) => files[i] === item).length
				tnr = 1- (matchCount / (files.length))

                console.log('Processed photos for label "' + label + '"')
		}

        } catch (e) {
            console.log(e)
        }

		console.log({tpr, tnr})

		return {tpr, tnr}
	}, 20)

	const sum = results.reduce((acc, val) => {
		return {tpr: acc.tpr+val.tpr, tnr: acc.tnr+val.tnr}
	}, {tpr:0, tnr:0})

    const averageTPR = sum.tpr/results.length
    const averageTNR = sum.tnr/results.length
    const balancedAccuracy =  (averageTPR+averageTNR)/2

	console.log({ averageTPR, averageTNR, balancedAccuracy})

	const worstLabels = Object.fromEntries(
		results
			.map((result, i) => [labels[i], result])
			.filter(([, result]) => (result.tpr+result.tnr) < balancedAccuracy)
	)

	console.log({ worstLabels })

	if (balancedAccuracy < 0.5) {
		process.exit(1)
	}
})()

function findPhotos(label) {
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

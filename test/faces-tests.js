const Flickr = require('flickr-sdk')
const { GOOGLE_IMG_SCRAP , GOOGLE_QUERY } = require('google-img-scrap');
const download = require('download')
const flatten = require('lodash/flatten')
const execa = require('execa')
const glob = require('fast-glob')
const Parallel = require('async-parallel')
const LABELS = require('./res/famous_people.json')

const PHOTOS_PER_LABEL = 10
const PHOTOS_OLDER_THAN = 1627464319 // 2021-07-28; for determinism
const FACE_DISTANCE_THRESHOLD = 0.45

;(async function() {
	const labels = LABELS.slice(0,20)
	const negativeLabel = 'peter'

	/*await Parallel.each(labels, async label => {
		try {
			let urls = await findPhotosGoogle('"' + label + '"', PHOTOS_PER_LABEL)
			await Promise.all(
				flatten(urls).map(url => download(url, 'temp_images/' + label))
			)
		} catch (e) {
			console.log('Error downloading photos for label "' + label + '"')
			console.log(e)
		}
	}, 1)

	try {
		let urls = await findPhotosGoogle(negativeLabel, PHOTOS_PER_LABEL * 3)
		await Promise.all(
			flatten(urls).map(url => download(url, 'temp_images/' + negativeLabel))
		)
	} catch (e) {
		console.log('Error downloading photos for label "'+negativeLabel+'"')
		console.log(e)
	}
*/

	const negativeFiles = await glob(['temp_images/'+negativeLabel+'/*'])
	if (!negativeFiles.length) {
		tpr = -1
		throw new Error('No photos found for label "'+negativeLabel+'"')
	}
	const { stdout: negativeStdout } = await execa('node', [__dirname + '/../src/classifier_faces.js'].concat(negativeFiles.slice(0, 10)))
	const negativePredictions = negativeStdout.split('\n')
		.map(line => JSON.parse(line))
	const negativeVectors = negativePredictions
		.flat()
		.map(pred => Array.from({length: 128, ...pred.vector}))


	const results = await Parallel.map(labels, async label => {
		// Calculate the true positive rate and true negative rate
		let tpr = 0, tnr = 0

		try {
			const files = await glob(['temp_images/' + label + '/*'])
			if (!files.length) {
				tpr = -1
				throw new Error('No photos found for label "' + label + '"')
			}
			const { stdout } = await execa('node', [__dirname + '/../src/classifier_faces.js'].concat(files))
			const predictions = stdout.split('\n')
				.map(line => JSON.parse(line))
			const vectors = predictions
				.flat()
				.map(pred => Array.from({length: 128, ...pred.vector}))

			const centroid = vectors
				.reduce((centroid, faceVector) =>
					addVectors(faceVector, centroid),
					Array(vectors[0].length).fill(0)
				)
				.map(el => el / vectors.length)

			const matches = vectors
				.map(faceVector => distanceVectors(faceVector, centroid))
				.map(distance => {console.log(distance); return distance})
				.filter(distance => distance < FACE_DISTANCE_THRESHOLD)

			tpr = matches.length / Math.max(vectors.length, 1) // avoid dividing by 0

			const negativeMatches = negativeVectors
				.map(faceVector => distanceVectors(faceVector, centroid))
				.map(distance => {console.log(distance); return distance})
				.filter(distance => distance > FACE_DISTANCE_THRESHOLD)

			tnr = negativeMatches.length / Math.max(negativeVectors.length, 1) // avoid dividing by 0

			console.log('Processed photos for label "' + label + '"')
		} catch (e) {
			tpr = -1
			console.log('Error processing photos for label "' + label + '"')
			console.log(e)
		}

		console.log({ tpr, tnr })

		return { tpr, tnr }
	}, 1)

	const sum = results.reduce((acc, val) => {
		return { tpr: acc.tpr + (val.tpr !== -1 ? val.tpr : 0), tnr: acc.tnr + (val.tnr !== -1 ? val.tnr : 0), count: acc.count + (val.tpr !== -1 ? 1 : 0)  }
	}, { tpr: 0, tnr: 0, count: 0 })

	const averageTPR = sum.tpr / results.length
	const averageTNR = sum.tnr / results.length

	console.log({ averageTPR, averageTNR })

	if (averageTPR < 0.8 || averageTNR < 0.8) {
		process.exit(1)
	}
})()

function findPhotos(label, amount = PHOTOS_PER_LABEL) {
	console.log('FLICKR search: '+label)
	return flickr.photos.search({
		text: label,
		per_page: amount,
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

async function findPhotosGoogle(label, amount= PHOTOS_PER_LABEL) {
	console.log('GOOGLE search: '+label)
	const results = await GOOGLE_IMG_SCRAP({
		search: label,
		query: {
			EXTENSION: GOOGLE_QUERY.EXTENSION.JPG,
			SIZE: GOOGLE_QUERY.SIZE.LARGE,
		},
		limit: amount,
	});

	return results.result.map(i => i.url)
}

function addVectors(a, b) {
	return a.map((el, i) => el + b[i])
}

function distanceVectors(a, b) {
	return a.map((el, i) => (el - b[i])**2).reduce((sum, el) => sum + el, 0)**(1/2)
}

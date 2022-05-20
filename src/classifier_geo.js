const path = require('path')
const geoReverse = require('geo-reverse')
const fsSync = require('fs')
const _ = require('lodash')
const exifer = require('exifer')
const gps = require('@exifer/gps')

if (process.argv.length < 3) throw new Error('Incorrect arguments: node classify.js ...<IMAGE_FILES> | node classify.js -')

/**
 *
 */
async function main() {
	const getStdin = (await import('get-stdin')).default

	const paths = process.argv[2] === '-'
		? (await getStdin()).split('\n')
		: process.argv.slice(2)

	for (const path of paths) {
		try {
			const metadata = await exifer(fsSync.readFileSync(path), { tags: { gps } })
			const lat = ConvertDMSToDD(metadata.GPSLatitude, metadata.GPSLatitudeRef)
			const long = ConvertDMSToDD(metadata.GPSLongitude, metadata.GPSLongitudeRef)
			const countries = geoReverse.country(lat, long, 'en')
			console.log(JSON.stringify(countries.map(c => c.name).filter(Boolean)))
		} catch (e) {
			console.error(e)
			console.log('[]')
		}
	}
}

Promise.resolve()
	.then(() => {
		return main()
	})
	.then(() => process.exit(0))
	.catch(e => {
		console.error(e)
		process.exit(1)
	})

/**
 * @param degrees
 * @param minutes
 * @param seconds
 * @param degrees."0"
 * @param degrees."1"
 * @param direction
 * @param degrees."2"
 */
function ConvertDMSToDD([degrees, minutes, seconds], direction) {
	let dd = degrees + minutes / 60 + seconds / (60 * 60)

	if (direction === 'South' || direction === 'West') {
		dd = dd * -1
	} // Don't do anything for N or E
	return dd
}

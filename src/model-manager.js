const download = require('download')
const path = require('path')
const tar = require('tar')
const VERSION = require('../package.json').version
const ref = process.env.GITHUB_REF ? process.env.GITHUB_REF : `refs/tags/v${VERSION}`

exports.downloadAll = async () => {
	await download(
		`https://github.com/nextcloud/recognize/archive/${ref}.tar.gz`,
		path.resolve(__dirname, '..'),
		{ filename: 'recognize.tar.gz' }
	)
	await new Promise(resolve =>
		tar.x({
			strip: 1,
			C: path.resolve(__dirname, '..'),
			file: path.resolve(__dirname, '..', 'recognize.tar.gz'),
			filter(path, entry) {
				return path.includes('models')
			},
		}, [], resolve)
	)
}

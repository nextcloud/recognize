const Flickr = require('flickr-sdk')
const download = require('download')
const fsSync = require('fs')
const YAML = require('yaml')
const flatten = require('lodash/flatten')
const uniq = require('lodash/uniq')
const execa = require('execa')
const glob = require('fast-glob');

const rules = YAML.parse(fsSync.readFileSync(__dirname + '/../src/rules.yml').toString('utf8'))

const PHOTOS_PER_LABEL = 50
const PHOTOS_OLDER_THAN = 1627464319 // 2021-07-28; for determinism
const flickr = new Flickr(process.env.FLICKR_API_KEY);

const labels = uniq(flatten(Object.entries(rules)
    .map(([key, entry]) =>
        entry.label?
            entry.categories?
                [entry.label].concat(entry.categories)
                : [entry.label]
            : []
    )))

;(async function() {
    const results = []
    for (const label of labels) {
        const urls = await findPhotos(label)
        await Promise.all(
            urls.map(url => download(url, 'temp_images/'+label))
        )
        const files = await glob(['temp_images/'+label+'/*'])
        if (!files.length) {
            console.log('No photos found for label "'+label+'"')
            results.push({matches: 0, misses: 0})
            continue
        }
        const {stdout} = await execa('node', [__dirname+'/../src/classifier.js'].concat(files))
        const results = stdout.split('\n')
            .map(line => JSON.parse(line))
        const matches = results
            .map((labels, i) => labels.includes(label)? files[i] : labels)

        const matchCount = matches.filter((item, i) => files[i] === item).length
        const missCount = files.length - matchCount
        const misses = matches
            .map((item) => typeof item === 'string'? null : item)
            .map((labels, i) => labels? [files[i], labels] : null)
            .filter(Boolean)

        console.log('Missed photos for label "'+label+'"')
        console.log(misses)

        results.push({matches: matchCount, misses: missCount})
    }

    const result = results.reduce(({matches1, misses1}, {matches2, misses2}) => {
        return {matches: matches1+matches2, misses: misses1+misses2}
    }, {matches: 0, misses: 0})
    const matchRate = result.matches/labels.length*PHOTOS_PER_LABEL
    console.log({matchRate})
    if (matchRate < 0.5) {
        process.exit(1)
    }
})()

function findPhotos(label) {
    return flickr.photos.search({
        text: label,
        per_page: PHOTOS_PER_LABEL,
        media: 'photos',
        content_type: 1,
        max_upload_date: PHOTOS_OLDER_THAN
    }).then(function (res) {
        if (res.body.stat === 'ok') {
            return res.body.photos.photo.map(photo => `https://live.staticflickr.com/${photo.server}/${photo.id}_${photo.secret}.jpg`)
        }else{
            console.log(res.body)
        }

    }).catch(function (err) {
        throw err
    });
}

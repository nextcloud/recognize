const uniq = require("lodash/uniq");
const flatten = require("lodash/flatten");
const YAML = require("yaml");
const fsSync = require("fs");
const rules = YAML.parse(fsSync.readFileSync(__dirname + '/../src/rules.yml').toString('utf8'))
const labels = uniq(flatten(Object.entries(rules)
    .map(([key, entry]) =>
        (entry.label
            ? [entry.label]
            : []).concat(entry.categories || [])
    )))
    .map(tag => tag[0].toUpperCase() + tag.substring(1))
    .map(tag => '"'+tag+'"')
    .join(',\n')
console.log('[')
console.log(labels)
console.log(']')

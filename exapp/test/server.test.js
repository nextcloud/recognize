/*
 * Copyright (c) 2024 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 *
 * Standalone functional test for the ExApp server. It does NOT require the real
 * recognize sources or TensorFlow: it points RECOGNIZE_SRC_DIR at a fixture dir
 * containing a stub `classifier_<model>.js` that echoes one JSON line per input
 * file, then exercises the real HTTP endpoints.
 *
 * Run with:  node exapp/test/server.test.js
 */

const http = require('http')
const fs = require('fs')
const os = require('os')
const path = require('path')

let failures = 0
let passed = 0
function assert(cond, msg) {
	if (cond) {
		passed++
		console.log('  ok  - ' + msg)
	} else {
		failures++
		console.error('  FAIL- ' + msg)
	}
}

// --- Build a fixture src dir with a stub classifier + model-manager -----------
const fixtureDir = fs.mkdtempSync(path.join(os.tmpdir(), 'recognize-exapp-fixture-'))
const srcDir = path.join(fixtureDir, 'src')
fs.mkdirSync(srcDir)

// Stub classifier: reads newline-separated paths from stdin, prints one JSON
// line per file echoing the file's first byte so we can verify ordering.
fs.writeFileSync(path.join(srcDir, 'classifier_imagenet.js'), `
const fs = require('fs')
let input = ''
process.stdin.on('data', d => { input += d })
process.stdin.on('end', () => {
	const paths = input.split('\\n').filter(p => p.trim() !== '')
	for (const p of paths) {
		const content = fs.readFileSync(p, 'utf8')
		console.log(JSON.stringify(['tag:' + content.trim()]))
	}
	process.exit(0)
})
`)
// A model-manager stub so /init does not blow up.
fs.writeFileSync(path.join(srcDir, 'model-manager.js'), 'exports.downloadAll = async () => {}\n')

const APP_SECRET = 's3cr3t'
const APP_PORT = 19123
// AppAPI sends AUTHORIZATION-APP-API as raw base64("userId:appSecret"), no "Basic " prefix.
const authHeader = Buffer.from('admin:' + APP_SECRET).toString('base64')

// --- Spawn the server under test ---------------------------------------------
const { spawn } = require('child_process')
const serverPath = path.join(__dirname, '..', 'server.js')
const child = spawn(process.execPath, [serverPath], {
	env: {
		...process.env,
		RECOGNIZE_SRC_DIR: srcDir,
		APP_SECRET,
		APP_PORT: String(APP_PORT),
		APP_HOST: '127.0.0.1',
	},
	stdio: ['ignore', 'pipe', 'pipe'],
})
child.stderr.on('data', d => process.stderr.write('[server] ' + d))

function request(method, pathname, headers = {}, body = null) {
	return new Promise((resolve, reject) => {
		const req = http.request({ host: '127.0.0.1', port: APP_PORT, method, path: pathname, headers }, res => {
			const chunks = []
			res.on('data', c => chunks.push(c))
			res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: Buffer.concat(chunks).toString('utf8') }))
		})
		req.on('error', reject)
		if (body) req.write(body)
		req.end()
	})
}

function buildMultipart(model, files) {
	const boundary = '----testboundary' + Math.floor(performance.now())
	const parts = []
	parts.push(Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="model"\r\n\r\n${model}\r\n`))
	files.forEach((f, i) => {
		parts.push(Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="files[${i}]"; filename="${f.filename}"\r\nContent-Type: application/octet-stream\r\n\r\n`))
		parts.push(Buffer.from(f.content))
		parts.push(Buffer.from('\r\n'))
	})
	parts.push(Buffer.from(`--${boundary}--\r\n`))
	return { boundary, body: Buffer.concat(parts) }
}

async function waitForServer() {
	for (let i = 0; i < 50; i++) {
		try {
			const r = await request('GET', '/heartbeat')
			if (r.status === 200) return
		} catch (e) { /* not up yet */ }
		await new Promise(r => setTimeout(r, 100))
	}
	throw new Error('server did not start')
}

async function run() {
	await waitForServer()

	// 1. heartbeat is unauthenticated and returns ok
	let r = await request('GET', '/heartbeat')
	assert(r.status === 200 && JSON.parse(r.body).status === 'ok', 'GET /heartbeat returns {status:ok} without auth')

	// 2. classify without auth is rejected
	const mp0 = buildMultipart('imagenet', [{ filename: 'a.jpg', content: 'A' }])
	r = await request('POST', '/classify', { 'Content-Type': `multipart/form-data; boundary=${mp0.boundary}` }, mp0.body)
	assert(r.status === 401, 'POST /classify without auth → 401')

	// 3. classify with auth: results returned, in order, one JSON line per file
	const mp = buildMultipart('imagenet', [
		{ filename: '0.jpg', content: 'zero' },
		{ filename: '1.jpg', content: 'one' },
		{ filename: '2.jpg', content: 'two' },
	])
	r = await request('POST', '/classify', {
		'Content-Type': `multipart/form-data; boundary=${mp.boundary}`,
		'Authorization-App-Api': authHeader,
	}, mp.body)
	assert(r.status === 200, 'POST /classify with auth → 200')
	const lines = r.body.split('\n').filter(l => l.trim() !== '')
	assert(lines.length === 3, `classify returns one line per file (got ${lines.length})`)
	const parsed = lines.map(l => JSON.parse(l))
	assert(JSON.stringify(parsed[0]) === JSON.stringify(['tag:zero'])
		&& JSON.stringify(parsed[1]) === JSON.stringify(['tag:one'])
		&& JSON.stringify(parsed[2]) === JSON.stringify(['tag:two']),
	'classify preserves file order (files[0..2] → zero,one,two)')

	// 4. files provided out of order are sorted back by index
	const boundary = '----oob' + Math.floor(performance.now())
	const oob = Buffer.concat([
		Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="model"\r\n\r\nimagenet\r\n`),
		Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="files[1]"; filename="1.jpg"\r\n\r\n`),
		Buffer.from('second'),
		Buffer.from(`\r\n--${boundary}\r\nContent-Disposition: form-data; name="files[0]"; filename="0.jpg"\r\n\r\n`),
		Buffer.from('first'),
		Buffer.from(`\r\n--${boundary}--\r\n`),
	])
	r = await request('POST', '/classify', {
		'Content-Type': `multipart/form-data; boundary=${boundary}`,
		'Authorization-App-Api': authHeader,
	}, oob)
	const oobLines = r.body.split('\n').filter(l => l.trim() !== '').map(l => JSON.parse(l))
	assert(r.status === 200 && JSON.stringify(oobLines[0]) === JSON.stringify(['tag:first'])
		&& JSON.stringify(oobLines[1]) === JSON.stringify(['tag:second']),
	'classify sorts files[1],files[0] back into 0,1 order')

	// 5. unsupported model rejected
	const bad = buildMultipart('definitely_not_a_model', [{ filename: 'a.jpg', content: 'A' }])
	r = await request('POST', '/classify', {
		'Content-Type': `multipart/form-data; boundary=${bad.boundary}`,
		'Authorization-App-Api': authHeader,
	}, bad.body)
	assert(r.status === 400, 'POST /classify with unsupported model → 400')

	// 6. no files rejected
	const nofiles = buildMultipart('imagenet', [])
	r = await request('POST', '/classify', {
		'Content-Type': `multipart/form-data; boundary=${nofiles.boundary}`,
		'Authorization-App-Api': authHeader,
	}, nofiles.body)
	assert(r.status === 400, 'POST /classify with no files → 400')

	// 7. non-multipart classify rejected
	r = await request('POST', '/classify', { 'Content-Type': 'application/json', 'Authorization-App-Api': authHeader }, '{}')
	assert(r.status === 400, 'POST /classify non-multipart → 400')

	// 8. lifecycle hooks
	r = await request('PUT', '/init', { 'Authorization-App-Api': authHeader })
	assert(r.status === 200, 'PUT /init → 200')
	r = await request('PUT', '/enabled', { 'Authorization-App-Api': authHeader })
	assert(r.status === 200, 'PUT /enabled → 200')

	// 9. wrong secret rejected
	const wrong = Buffer.from('admin:wrongsecret').toString('base64')
	r = await request('PUT', '/enabled', { 'Authorization-App-Api': wrong })
	assert(r.status === 401, 'wrong secret → 401')

	// 10. unknown route
	r = await request('GET', '/nope', { 'Authorization-App-Api': authHeader })
	assert(r.status === 404, 'unknown route → 404')
}

run()
	.catch(e => { console.error(e); failures++ })
	.finally(() => {
		child.kill()
		fs.rmSync(fixtureDir, { recursive: true, force: true })
		console.log(`\n${passed} passed, ${failures} failed`)
		process.exit(failures === 0 ? 0 : 1)
	})

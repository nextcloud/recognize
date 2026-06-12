/*
 * Copyright (c) 2024 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 *
 * Minimal HTTP server that turns the recognize classifier scripts into a
 * Nextcloud External App (ExApp) classification backend.
 *
 * It deliberately reuses the *unmodified* `classifier_<model>.js` scripts from
 * the recognize `src/` directory: they read newline-separated file paths from
 * stdin and emit one JSON line per file to stdout. This server simply receives
 * the uploaded image files over HTTP, spawns the right classifier script, and
 * streams its stdout back. See ./README.md for the full picture.
 */

const http = require('http')
const fs = require('fs')
const os = require('os')
const path = require('path')
const crypto = require('crypto')
const { spawn } = require('child_process')

// The recognize sources are copied into ../src relative to this file in the image.
const SRC_DIR = process.env.RECOGNIZE_SRC_DIR || path.join(__dirname, 'src')

const PORT = parseInt(process.env.APP_PORT || '9000', 10)
const HOST = process.env.APP_HOST || '0.0.0.0'
// Shared secret injected by AppAPI on deploy. Requests must present it.
const APP_SECRET = process.env.APP_SECRET || ''
const APP_ID = process.env.APP_ID || 'recognize_exapp'

const ALLOWED_MODELS = ['imagenet', 'landmarks', 'faces', 'movinet', 'musicnn']

/**
 * Verify the AppAPI authentication headers.
 * AppAPI signs requests with the shared APP_SECRET; we compare it in constant time.
 *
 * @param {http.IncomingMessage} req
 * @return {boolean}
 */
function isAuthenticated(req) {
	if (!APP_SECRET) {
		// No secret configured (e.g. local manual testing): allow.
		return true
	}
	const provided = req.headers['authorization-app-api'] || ''
	// AppAPI sends base64("userId:appSecret"); we only check the secret part.
	let secret = ''
	try {
		const decoded = Buffer.from(String(provided), 'base64').toString('utf8')
		secret = decoded.slice(decoded.indexOf(':') + 1)
	} catch (e) {
		return false
	}
	const a = Buffer.from(secret)
	const b = Buffer.from(APP_SECRET)
	return a.length === b.length && crypto.timingSafeEqual(a, b)
}

/**
 * Parse a multipart/form-data body into { fields, files }.
 * Minimal parser sufficient for the recognize payload (a `model` field and
 * `files[N]` file parts). Avoids pulling in an extra dependency.
 *
 * @param {Buffer} body
 * @param {string} boundary
 */
function parseMultipart(body, boundary) {
	const fields = {}
	const files = []
	const delimiter = Buffer.from('--' + boundary)
	let start = body.indexOf(delimiter)
	while (start !== -1) {
		const next = body.indexOf(delimiter, start + delimiter.length)
		if (next === -1) {
			break
		}
		// part is between this delimiter and the next, skipping the trailing CRLF after the boundary
		let part = body.subarray(start + delimiter.length, next)
		// strip leading CRLF
		if (part[0] === 0x0d && part[1] === 0x0a) {
			part = part.subarray(2)
		}
		const headerEnd = part.indexOf('\r\n\r\n')
		if (headerEnd !== -1) {
			const rawHeaders = part.subarray(0, headerEnd).toString('utf8')
			// strip trailing CRLF of the content
			let content = part.subarray(headerEnd + 4)
			if (content.length >= 2 && content[content.length - 2] === 0x0d && content[content.length - 1] === 0x0a) {
				content = content.subarray(0, content.length - 2)
			}
			const nameMatch = rawHeaders.match(/name="([^"]*)"/i)
			const filenameMatch = rawHeaders.match(/filename="([^"]*)"/i)
			const name = nameMatch ? nameMatch[1] : null
			if (name !== null) {
				if (filenameMatch) {
					files.push({ name, filename: filenameMatch[1], content })
				} else {
					fields[name] = content.toString('utf8')
				}
			}
		}
		start = next
	}
	return { fields, files }
}

/**
 * Run the recognize classifier script for the given model on the given file paths.
 * Resolves with the raw stdout (newline-delimited JSON), one line per file.
 *
 * @param {string} model
 * @param {string[]} filePaths
 * @return {Promise<string>}
 */
function runClassifier(model, filePaths) {
	return new Promise((resolve, reject) => {
		const script = path.join(SRC_DIR, `classifier_${model}.js`)
		if (!fs.existsSync(script)) {
			reject(new Error(`Unknown classifier script for model "${model}"`))
			return
		}
		const env = { ...process.env }
		if (process.env.RECOGNIZE_GPU === 'true') {
			env.RECOGNIZE_GPU = 'true'
		}
		if (process.env.RECOGNIZE_PUREJS === 'true') {
			env.RECOGNIZE_PUREJS = 'true'
		}
		const child = spawn(process.execPath, [script, '-'], { cwd: SRC_DIR, env })
		let stdout = ''
		let stderr = ''
		child.stdout.on('data', d => { stdout += d.toString('utf8') })
		child.stderr.on('data', d => { stderr += d.toString('utf8') })
		child.on('error', reject)
		child.on('close', code => {
			if (code !== 0) {
				reject(new Error(`Classifier process exited with code ${code}: ${stderr}`))
				return
			}
			resolve(stdout)
		})
		child.stdin.write(filePaths.join('\n'))
		child.stdin.end()
	})
}

/**
 * @param {http.IncomingMessage} req
 * @return {Promise<Buffer>}
 */
function readBody(req) {
	return new Promise((resolve, reject) => {
		const chunks = []
		req.on('data', c => chunks.push(c))
		req.on('end', () => resolve(Buffer.concat(chunks)))
		req.on('error', reject)
	})
}

function sendJson(res, status, obj) {
	const body = JSON.stringify(obj)
	res.writeHead(status, { 'Content-Type': 'application/json' })
	res.end(body)
}

const server = http.createServer(async (req, res) => {
	const url = new URL(req.url, `http://${req.headers.host}`)

	// Liveness probe — must be unauthenticated per AppAPI conventions.
	if (req.method === 'GET' && url.pathname === '/heartbeat') {
		sendJson(res, 200, { status: 'ok' })
		return
	}

	if (!isAuthenticated(req)) {
		sendJson(res, 401, { error: 'Unauthorized' })
		return
	}

	// AppAPI lifecycle hooks.
	if (req.method === 'PUT' && url.pathname === '/init') {
		// Kick off model download in the background so first classification is fast.
		try {
			const { downloadAll } = require(path.join(SRC_DIR, 'model-manager.js'))
			downloadAll().catch(e => console.error('Model download failed', e))
		} catch (e) {
			console.error('Could not start model download', e)
		}
		sendJson(res, 200, {})
		return
	}
	if (req.method === 'PUT' && url.pathname === '/enabled') {
		sendJson(res, 200, {})
		return
	}

	if (req.method === 'POST' && url.pathname === '/classify') {
		const contentType = req.headers['content-type'] || ''
		const boundaryMatch = contentType.match(/boundary=(.+)$/)
		if (!boundaryMatch) {
			sendJson(res, 400, { error: 'Expected multipart/form-data' })
			return
		}
		let tmpDir
		try {
			const body = await readBody(req)
			const { fields, files } = parseMultipart(body, boundaryMatch[1].replace(/^"|"$/g, ''))
			const model = fields.model
			if (!ALLOWED_MODELS.includes(model)) {
				sendJson(res, 400, { error: `Unsupported model "${model}"` })
				return
			}
			if (files.length === 0) {
				sendJson(res, 400, { error: 'No files provided' })
				return
			}
			// Preserve the upload order (files[0], files[1], ...) so results map back correctly.
			files.sort((a, b) => {
				const ai = parseInt((a.name.match(/\[(\d+)\]/) || [])[1] || '0', 10)
				const bi = parseInt((b.name.match(/\[(\d+)\]/) || [])[1] || '0', 10)
				return ai - bi
			})

			tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'recognize-exapp-'))
			const filePaths = files.map((f, i) => {
				const ext = path.extname(f.filename || '') || '.jpg'
				const p = path.join(tmpDir, `${i}${ext}`)
				fs.writeFileSync(p, f.content)
				return p
			})

			const output = await runClassifier(model, filePaths)
			res.writeHead(200, { 'Content-Type': 'application/x-ndjson' })
			res.end(output)
		} catch (e) {
			console.error('Classification failed', e)
			sendJson(res, 500, { error: String(e.message || e) })
		} finally {
			if (tmpDir) {
				fs.rm(tmpDir, { recursive: true, force: true }, () => {})
			}
		}
		return
	}

	sendJson(res, 404, { error: 'Not found' })
})

server.listen(PORT, HOST, () => {
	console.log(`recognize ExApp "${APP_ID}" listening on ${HOST}:${PORT}`)
})

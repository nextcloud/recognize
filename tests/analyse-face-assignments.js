#!/usr/bin/env node
/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 *
 * Scores a face clustering run against the IMDb-Face ground truth annotations.
 *
 * Every rate here is normalised against the *ground truth* (the annotations whose
 * photo actually exists on disk), never against the run's own output. The previous
 * inline version of this script derived its identity pool from out.json, i.e. from
 * the clustered faces themselves, which made all per-identity metrics incomparable
 * between two runs: identities the run failed to cluster at all silently dropped
 * out of the denominator instead of scoring zero, and identitiesWithClustersRate
 * was ~1.0 by construction.
 *
 * Inputs (all via env, so the same script serves both CI jobs and run-clustering-test.sh):
 *   CSV_PATH              IMDb-Face.csv
 *   PHOTOS_TXT            "<Identity>/<file>" per line, one per photo on disk (find -printf '%P\n')
 *   DETECTIONS_TXT        "x|y|width|height|path" per line, every stored detection
 *   CLUSTERS_JSON         out.json, the parsed DAV faces listing
 *   DETECTED_FACES_TOTAL  total detection count from the database
 *   LABEL                 name of the run, used as the summary heading
 *   MIN_IOU                        match threshold, default 0.3
 *   MIN_COMBINED_SCORE             gate, default 0 (report only, see workflow comment)
 *   MIN_CLUSTER_TARGET_ACCURACY    gate, default 0.85
 *   METRICS_JSON_OUT      optional path to write the metrics as JSON
 *   GITHUB_STEP_SUMMARY   optional path to append a markdown table to
 */
'use strict';

const fs = require('fs');

function env(name, fallback) {
	const value = process.env[name];
	if (value === undefined || value === '') {
		if (fallback === undefined) {
			console.error(`Missing required env variable ${name}`);
			process.exit(2);
		}
		return fallback;
	}
	return value;
}

const CSV_PATH = env('CSV_PATH');
const PHOTOS_TXT = env('PHOTOS_TXT');
const DETECTIONS_TXT = env('DETECTIONS_TXT');
const CLUSTERS_JSON = env('CLUSTERS_JSON');
const DETECTED_FACES_TOTAL = parseInt(env('DETECTED_FACES_TOTAL'), 10);
const LABEL = env('LABEL', 'clustering run');
const MIN_IOU = parseFloat(env('MIN_IOU', '0.3'));
const MIN_COMBINED_SCORE = parseFloat(env('MIN_COMBINED_SCORE', '0'));
const MIN_CLUSTER_TARGET_ACCURACY = parseFloat(env('MIN_CLUSTER_TARGET_ACCURACY', '0.85'));

const COLUMN_NAME = 0;
const COLUMN_RECT = 3;
const COLUMN_DIMS = 4;
const COLUMN_URL = 5;

/** Filename without directory, query string or extension, for matching CSV urls against files on disk. */
function stem(pathOrUrl) {
	const base = pathOrUrl.split('?')[0].split('#')[0].split('/').pop() ?? '';
	const dot = base.lastIndexOf('.');
	return (dot > 0 ? base.slice(0, dot) : base).toLowerCase();
}

function intersectionOverUnion(a, b) {
	const left = Math.max(a.x, b.x);
	const top = Math.max(a.y, b.y);
	const right = Math.min(a.x + a.width, b.x + b.width);
	const bottom = Math.min(a.y + a.height, b.y + b.height);
	if (right <= left || bottom <= top) {
		return 0;
	}
	const intersection = (right - left) * (bottom - top);
	const union = a.width * a.height + b.width * b.height - intersection;
	return union > 0 ? intersection / union : 0;
}

function mean(values) {
	return values.length === 0 ? 0 : values.reduce((acc, val) => acc + val, 0) / values.length;
}

function median(values) {
	if (values.length === 0) {
		return 0;
	}
	const sorted = [...values].sort((a, b) => a - b);
	const middle = Math.floor(sorted.length / 2);
	return sorted.length % 2 === 0 ? (sorted[middle - 1] + sorted[middle]) / 2 : sorted[middle];
}

// ---------------------------------------------------------------------------
// 1. Photos on disk. This is the only run-independent inventory we have, so it
//    defines which annotations can possibly be found and which identities count.
// ---------------------------------------------------------------------------

const photosByIdentity = new Map();
let totalPhotos = 0;
for (const line of fs.readFileSync(PHOTOS_TXT, 'utf8').split('\n')) {
	const relative = line.trim();
	if (relative === '') {
		continue;
	}
	const parts = relative.split('/');
	if (parts.length < 2) {
		continue;
	}
	const identity = parts[0];
	if (!photosByIdentity.has(identity)) {
		photosByIdentity.set(identity, new Set());
	}
	photosByIdentity.get(identity).add(stem(relative));
	totalPhotos++;
}

// ---------------------------------------------------------------------------
// 2. Ground truth: every CSV annotation whose photo is on disk.
// ---------------------------------------------------------------------------

const groundTruth = [];
const seenAnnotations = new Set();
let malformedAnnotations = 0;
const csv = fs.readFileSync(CSV_PATH, 'utf8').split('\n');
for (let i = 1; i < csv.length; i++) {
	const row = csv[i].split(',');
	if (row.length <= COLUMN_URL) {
		continue;
	}
	const identity = row[COLUMN_NAME];
	const photos = photosByIdentity.get(identity);
	if (photos === undefined) {
		continue;
	}
	const file = stem(row[COLUMN_URL]);
	if (!photos.has(file)) {
		continue;
	}
	const key = `${identity}/${file}/${row[COLUMN_RECT]}`;
	if (seenAnnotations.has(key)) {
		continue;
	}
	seenAnnotations.add(key);

	// rect is "x1 y1 x2 y2" in pixels, dims is "height width".
	const rect = row[COLUMN_RECT].trim().split(/\s+/).map(Number);
	const dims = row[COLUMN_DIMS].trim().split(/\s+/).map(Number);
	const imageHeight = dims[0];
	const imageWidth = dims[1];
	const box = {
		x: rect[0] / imageWidth,
		y: rect[1] / imageHeight,
		width: (rect[2] - rect[0]) / imageWidth,
		height: (rect[3] - rect[1]) / imageHeight,
	};
	if (!Object.values(box).every(Number.isFinite) || box.width <= 0 || box.height <= 0 || box.width > 1.5 || box.height > 1.5) {
		malformedAnnotations++;
		continue;
	}
	groundTruth.push({ identity, file, box });
}

if (groundTruth.length === 0) {
	console.error('No ground truth annotations matched the photos on disk - check CSV_PATH and PHOTOS_TXT');
	process.exit(2);
}

/** identity -> annotations, the run-independent per-identity denominator. */
const groundTruthByIdentity = new Map();
for (const face of groundTruth) {
	if (!groundTruthByIdentity.has(face.identity)) {
		groundTruthByIdentity.set(face.identity, []);
	}
	groundTruthByIdentity.get(face.identity).push(face);
}

// ---------------------------------------------------------------------------
// 3. Detections, keyed by photo. out.txt holds every stored detection; the
//    x/y/width/height are normalised fractions of the image (relativeBox).
// ---------------------------------------------------------------------------

const detectionsByPhoto = new Map();
for (const line of fs.readFileSync(DETECTIONS_TXT, 'utf8').split('\n')) {
	const row = line.trim().split('|');
	if (row.length < 5) {
		continue;
	}
	const [x, y, width, height] = row.slice(0, 4).map(Number);
	// path is "files/IMDb-Face/<Identity>/<file>"
	const parts = row[4].split('/');
	if (parts.length < 4) {
		continue;
	}
	const key = `${parts[2]}/${stem(row[4])}`;
	if (!detectionsByPhoto.has(key)) {
		detectionsByPhoto.set(key, []);
	}
	detectionsByPhoto.get(key).push({ x, y, width, height });
}

// ---------------------------------------------------------------------------
// 4. Cluster assignments. out.json lists a photo once per cluster it appears
//    in, and each entry carries *every* detection of that photo along with its
//    own clusterId - so dedupe by box and read the assignment per detection
//    rather than trusting the href of the listing or detection index [0].
// ---------------------------------------------------------------------------

const clustered = JSON.parse(fs.readFileSync(CLUSTERS_JSON, 'utf8'));
const clusteredDetectionsByPhoto = new Map();
const identityLabelsByCluster = new Map();
const seenDetections = new Set();
for (const entry of clustered) {
	if (typeof entry?.realpath !== 'string' || !Array.isArray(entry['face-detections'])) {
		continue;
	}
	// realpath is "/admin/files/IMDb-Face/<Identity>/<file>"
	const parts = entry.realpath.split('/');
	if (parts.length < 6) {
		continue;
	}
	const identity = parts[4];
	const key = `${identity}/${stem(entry.realpath)}`;
	for (const detection of entry['face-detections']) {
		const box = {
			x: Number(detection.x),
			y: Number(detection.y),
			width: Number(detection.width),
			height: Number(detection.height),
		};
		const clusterId = detection.clusterId === null || detection.clusterId === undefined
			? -1
			: parseInt(String(detection.clusterId), 10);
		const detectionKey = `${key}/${box.x}/${box.y}/${box.width}/${box.height}/${clusterId}`;
		if (seenDetections.has(detectionKey)) {
			continue;
		}
		seenDetections.add(detectionKey);

		if (!clusteredDetectionsByPhoto.has(key)) {
			clusteredDetectionsByPhoto.set(key, []);
		}
		clusteredDetectionsByPhoto.get(key).push({ ...box, clusterId });

		if (clusterId > 0) {
			if (!identityLabelsByCluster.has(clusterId)) {
				identityLabelsByCluster.set(clusterId, []);
			}
			identityLabelsByCluster.get(clusterId).push(identity);
		}
	}
}

// ---------------------------------------------------------------------------
// 5. Match every ground truth annotation to a detection by IoU.
// ---------------------------------------------------------------------------

const bestIous = [];
const centreOffsets = [];
const areaRatios = [];
let detectedTargetFaces = 0;
let clusteredTargetFaces = 0;
let legacyDetectedTargetFaces = 0;
/** identity -> clusterId -> number of that identity's target faces in the cluster */
const targetFacesByIdentityAndCluster = new Map();
/** identity -> number of that identity's annotations that were detected at all */
const detectedTargetFacesByIdentity = new Map();
/** clusterId -> identity labels of the target faces in it */
const targetLabelsByCluster = new Map();

for (const face of groundTruth) {
	const key = `${face.identity}/${face.file}`;
	const candidates = detectionsByPhoto.get(key) ?? [];

	let best = null;
	let bestIou = 0;
	for (const candidate of candidates) {
		const iou = intersectionOverUnion(face.box, candidate);
		if (iou > bestIou) {
			bestIou = iou;
			best = candidate;
		}
		// The old metric: top left corner within 5% of the image in both axes.
		// Kept as a diagnostic so a change in box convention is visible as a
		// divergence between the two numbers rather than as lost recall.
		if (Math.abs(candidate.x - face.box.x) < 0.05 && Math.abs(candidate.y - face.box.y) < 0.05) {
			legacyDetectedTargetFaces++;
		}
	}
	bestIous.push(bestIou);
	if (best !== null && bestIou >= 0.1) {
		centreOffsets.push(Math.hypot(
			(best.x + best.width / 2) - (face.box.x + face.box.width / 2),
			(best.y + best.height / 2) - (face.box.y + face.box.height / 2),
		));
		areaRatios.push((best.width * best.height) / (face.box.width * face.box.height));
	}
	if (bestIou < MIN_IOU) {
		continue;
	}
	detectedTargetFaces++;
	detectedTargetFacesByIdentity.set(face.identity, (detectedTargetFacesByIdentity.get(face.identity) ?? 0) + 1);

	// Which cluster did *this* face end up in, if any?
	let clusterId = -1;
	let clusteredIou = MIN_IOU;
	for (const candidate of clusteredDetectionsByPhoto.get(key) ?? []) {
		const iou = intersectionOverUnion(face.box, candidate);
		if (iou >= clusteredIou && candidate.clusterId > 0) {
			clusteredIou = iou;
			clusterId = candidate.clusterId;
		}
	}
	if (clusterId <= 0) {
		continue;
	}
	clusteredTargetFaces++;

	if (!targetFacesByIdentityAndCluster.has(face.identity)) {
		targetFacesByIdentityAndCluster.set(face.identity, new Map());
	}
	const byCluster = targetFacesByIdentityAndCluster.get(face.identity);
	byCluster.set(clusterId, (byCluster.get(clusterId) ?? 0) + 1);

	if (!targetLabelsByCluster.has(clusterId)) {
		targetLabelsByCluster.set(clusterId, []);
	}
	targetLabelsByCluster.get(clusterId).push(face.identity);
}

// ---------------------------------------------------------------------------
// 6. Metrics. Denominators are the disk inventory and the ground truth only.
// ---------------------------------------------------------------------------

function purity(labels) {
	const counts = new Map();
	for (const label of labels) {
		counts.set(label, (counts.get(label) ?? 0) + 1);
	}
	return Math.max(...counts.values()) / labels.length;
}

const clusterAccuracies = [...identityLabelsByCluster.values()].map(purity);
const clusterTargetAccuracies = [...targetLabelsByCluster.values()]
	.filter(labels => labels.length > 1)
	.map(purity);

const clusteredFaces = [...identityLabelsByCluster.values()].reduce((acc, labels) => acc + labels.length, 0);

// Identities that have at least one annotation on disk. Unlike the old
// identitiesWithDetections this does not depend on what the run produced, so an
// identity the run missed entirely scores 0 instead of vanishing.
const scoreableIdentities = [...groundTruthByIdentity.keys()];
const identityRecalls = [];
let identitiesWithDetections = 0;
let identitiesWithClusters = 0;
for (const identity of scoreableIdentities) {
	const annotations = groundTruthByIdentity.get(identity);
	const byCluster = targetFacesByIdentityAndCluster.get(identity);
	const largest = byCluster === undefined ? 0 : Math.max(...byCluster.values());
	identityRecalls.push(largest / annotations.length);
	if ((detectedTargetFacesByIdentity.get(identity) ?? 0) > 0) {
		identitiesWithDetections++;
	}
	// An identity counts as recovered only if at least two of its faces landed
	// in one and the same cluster - a single face is not an album.
	if (largest > 1) {
		identitiesWithClusters++;
	}
}

const averageClusterTargetAccuracy = mean(clusterTargetAccuracies);
const identitiesWithClustersRate = identitiesWithClusters / scoreableIdentities.length;
const clusteredTargetFacesByIdentityRate = mean(identityRecalls);
const clusteredTargetFacesRate = clusteredTargetFaces / groundTruth.length;
const combinedScore = (
	averageClusterTargetAccuracy
	* identitiesWithClustersRate
	* clusteredTargetFacesByIdentityRate
	* clusteredTargetFacesRate
) ** (1 / 4);

const metrics = {
	// Ground truth (identical for every run over the same dataset)
	totalPhotos,
	identitiesWithPhotos: photosByIdentity.size,
	groundTruthFaces: groundTruth.length,
	scoreableIdentities: scoreableIdentities.length,
	averageGroundTruthFacesPerIdentity: groundTruth.length / scoreableIdentities.length,

	// Detection
	detectedFaces: DETECTED_FACES_TOTAL,
	detectedFacesPerPhoto: DETECTED_FACES_TOTAL / totalPhotos,
	detectedTargetFaces,
	detectionRecall: detectedTargetFaces / groundTruth.length,
	identityDetectionRate: identitiesWithDetections / scoreableIdentities.length,

	// Clustering
	clusteredFaces,
	clusteredFacesRate: DETECTED_FACES_TOTAL > 0 ? clusteredFaces / DETECTED_FACES_TOTAL : 0,
	clusters: identityLabelsByCluster.size,
	clusteredTargetFaces,
	clusteredTargetFacesRate,
	clusteredOfDetectedTargetFacesRate: detectedTargetFaces > 0 ? clusteredTargetFaces / detectedTargetFaces : 0,
	clusteredTargetFacesByIdentityRate,
	identitiesWithClusters,
	identitiesWithClustersRate,

	// Purity
	averageClusterAccuracy: mean(clusterAccuracies),
	averageClusterTargetAccuracy,
	shitClusterRate: clusterAccuracies.length === 0
		? 0
		: clusterAccuracies.filter(accuracy => accuracy < 0.5).length / clusterAccuracies.length,
	targetedShitClusterRate: clusterTargetAccuracies.length === 0
		? 0
		: clusterTargetAccuracies.filter(accuracy => accuracy < 0.5).length / clusterTargetAccuracies.length,

	// Composite
	weightedAccuracy: mean(clusterAccuracies) * (DETECTED_FACES_TOTAL > 0 ? clusteredFaces / DETECTED_FACES_TOTAL : 0),
	weightedTargetAccuracy: averageClusterTargetAccuracy * clusteredTargetFacesRate,
	combinedScore,

	// Box matching diagnostics: if a backend reports boxes in a different
	// convention (tighter crops, different origin) detectionRecall drops without
	// the detector actually missing anything. These numbers tell the two apart.
	medianBestIou: median(bestIous),
	meanBestIou: mean(bestIous),
	groundTruthFacesWithoutAnyOverlap: bestIous.filter(iou => iou < 0.1).length,
	meanCentreOffset: mean(centreOffsets),
	meanBoxAreaRatio: mean(areaRatios),
	legacyDetectedTargetFaces,
	malformedAnnotations,
};

for (const [name, value] of Object.entries(metrics)) {
	console.log(`${name}: ${typeof value === 'number' && !Number.isInteger(value) ? value.toFixed(4) : value}`);
}

if (process.env.METRICS_JSON_OUT) {
	fs.writeFileSync(process.env.METRICS_JSON_OUT, JSON.stringify(metrics, null, 2));
}

if (process.env.GITHUB_STEP_SUMMARY) {
	const rows = Object.entries(metrics)
		.map(([name, value]) => {
			const label = name.replace(/([A-Z])/g, ' $1').replace(/^./, char => char.toUpperCase());
			const formatted = typeof value === 'number' && !Number.isInteger(value)
				? value.toFixed(4)
				: String(value);
			return `| ${label} | ${formatted} |`;
		})
		.join('\n');
	fs.appendFileSync(
		process.env.GITHUB_STEP_SUMMARY,
		`## ${LABEL}\n\n| Metric | Value |\n| :--- | ---: |\n${rows}\n\n`,
	);
}

// ---------------------------------------------------------------------------
// 7. Gate.
// ---------------------------------------------------------------------------

const failures = [];
if (!Number.isFinite(combinedScore) || combinedScore > 1.0) {
	failures.push(`combinedScore ${combinedScore} is not a valid score`);
}
if (combinedScore < MIN_COMBINED_SCORE) {
	failures.push(`combinedScore ${combinedScore.toFixed(4)} < MIN_COMBINED_SCORE ${MIN_COMBINED_SCORE}`);
}
if (metrics.averageClusterTargetAccuracy < MIN_CLUSTER_TARGET_ACCURACY) {
	failures.push(`averageClusterTargetAccuracy ${metrics.averageClusterTargetAccuracy.toFixed(4)} < MIN_CLUSTER_TARGET_ACCURACY ${MIN_CLUSTER_TARGET_ACCURACY}`);
}
if (clusteredFaces === 0) {
	failures.push('no faces were clustered at all');
}

if (failures.length > 0) {
	console.log('Benchmark result: Bad');
	for (const failure of failures) {
		console.log(`  - ${failure}`);
	}
	process.exit(1);
}
console.log('Benchmark result: Good');

<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright (c) 2014, Stefan Klemm
 */

namespace OCA\Recognize\AppInfo;

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
	'routes' => [
		//internal ADMIN API
		['name' => 'admin#reset', 'url' => '/admin/reset', 'verb' => 'GET'],
		['name' => 'admin#clearAllJobs', 'url' => '/admin/clearJobs', 'verb' => 'GET'],
		['name' => 'admin#recrawl', 'url' => '/admin/recrawl', 'verb' => 'GET'],
		['name' => 'admin#reset_faces', 'url' => '/admin/resetFaces', 'verb' => 'GET'],
		['name' => 'admin#count', 'url' => '/admin/count', 'verb' => 'GET'],
		['name' => 'admin#countQueued', 'url' => '/admin/countQueued', 'verb' => 'GET'],
		['name' => 'admin#count_missed', 'url' => '/admin/countMissed', 'verb' => 'GET'],
		['name' => 'admin#avx', 'url' => '/admin/avx', 'verb' => 'GET'],
		['name' => 'admin#platform', 'url' => '/admin/platform', 'verb' => 'GET'],
		['name' => 'admin#musl', 'url' => '/admin/musl', 'verb' => 'GET'],
		['name' => 'admin#nice', 'url' => '/admin/nice', 'verb' => 'GET'],
		['name' => 'admin#nodejs', 'url' => '/admin/nodejs', 'verb' => 'GET'],
		['name' => 'admin#libtensorflow', 'url' => '/admin/libtensorflow', 'verb' => 'GET'],
		['name' => 'admin#wasmtensorflow', 'url' => '/admin/wasmtensorflow', 'verb' => 'GET'],
		['name' => 'admin#gputensorflow', 'url' => '/admin/gputensorflow', 'verb' => 'GET'],
		['name' => 'admin#cron', 'url' => '/admin/cron', 'verb' => 'GET'],
		['name' => 'admin#hasJobs', 'url' => '/admin/jobs/{task}', 'verb' => 'GET'],
		['name' => 'admin#get_setting', 'url' => '/admin/settings/{setting}', 'verb' => 'GET'],
		['name' => 'admin#set_setting', 'url' => '/admin/settings/{setting}', 'verb' => 'PUT'],
	],
];

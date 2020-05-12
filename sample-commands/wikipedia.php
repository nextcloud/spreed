<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

if (PHP_SAPI !== 'cli') {
	// Only allow access via the console
	exit;
}

if ($argc < 2) {
	echo 'Missing search term in call to wikipedia.php';
	return 1;
}

$searchTerm = $argv[1];

if ($searchTerm === '--help') {
	echo '/wiki - A simple command to find wikipedia articles for a term' . "\n";
	echo "\n";
	echo 'Example: /wiki Nextcloud' . "\n";
	$searchTerm = 'Nextcloud';
}


$endpoint = 'https://en.wikipedia.org/w/api.php';
$parameters = [
	'action' => 'query',
	'generator' => 'prefixsearch',
	'gpssearch' => $searchTerm,
	'prop' => 'description|info',
	'format' => 'json',
	'formatversion' => 2,
	'inprop' => 'url',
];
$content = file_get_contents($endpoint . '?' . http_build_query($parameters));
$results = json_decode($content, true);

if (empty($results['query']['pages'])) {
	echo 'Wikipedia did not have any results for "' . $searchTerm . '" :(' . "\n";
	return;
}

$pages = $results['query']['pages'];
$numArticles = count($pages);

$response = 'Wikipedia search results for "' . $searchTerm . '":' . "\n";
$maxArticles = $numArticles > 7 ? 5 : $numArticles;
$length = (int) ((800 - strlen($response)) / $maxArticles);

foreach ($pages as $key => $page) {
	if ($key >= $maxArticles) {
		break;
	}

	$link = " - {$page['canonicalurl']}\n";
	$remainingLength = max(strlen($page['title']),$length - strlen($link));
	if ($remainingLength < strlen("* {$page['title']} - {$page['description']}")) {
		$response .= substr("* {$page['title']} - {$page['description']}", 0, $remainingLength) . '…' . $link;
	} else {
		$response .= "* {$page['title']} - {$page['description']}" . $link;
	}
}

if ($maxArticles < $numArticles) {
	$response .= '* and ' . ($numArticles - $maxArticles) ." more articles found\n";
}

echo($response);

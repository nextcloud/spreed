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
	echo 'Missing search term in call to hackernews.php';
	return 1;
}

$mode = $argv[1];

if ($mode === '--help' || !in_array($mode, ['top', 'new', 'best', ''], true)) {
	echo '/hackernews - A simple command to list the Top 5 top, new or best stories' . "\n";
	echo "\n";
	echo 'Example: /hackernews top' . "\n";
	return;
}

$mode = $mode ?: 'top';
$endpoint = 'https://hacker-news.firebaseio.com/v0/' . $mode . 'stories.json';
$content = file_get_contents($endpoint);
$results = json_decode($content, true);
$stories = array_slice($results, 0, 5);

$response = 'Hackernews ' . ucfirst($mode) . '  5:' . "\n";
$length = 120;

foreach ($stories as $storyId) {
	$endpoint = 'https://hacker-news.firebaseio.com/v0/item/' . $storyId . '.json';
	$content = file_get_contents($endpoint);
	$result = json_decode($content, true);

	$link = " - {$result['url']}\n";
	$remainingLength = max(strlen($result['title']),$length - strlen($link));
	if ($remainingLength < strlen('* ' . $result['title'])) {
		$response .= substr('* ' . $result['title'], 0, $remainingLength) . '…' . $link;
	} else {
		$response .= '* ' . $result['title'] . $link;
	}
}

$page = 'news';
if ($mode === 'new') {
	$page = 'newest';
} else if ($mode === 'best') {
	$page = 'best';
}

$response .= "Find more at https://news.ycombinator.com/$page";

echo ($response);

<?php
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

$receivedRequestsFile = sys_get_temp_dir() . '/fake-nextcloud-talk-signaling-requests';
$nextResponseFile = sys_get_temp_dir() . '/fake-nextcloud-talk-signaling-response';

if (preg_match('/\/api\/v1\/room\/([^\/]+)/', $_SERVER['REQUEST_URI'], $matches)) {
	if (empty($_SERVER['HTTP_SPREED_SIGNALING_RANDOM'])) {
		error_log('fake-signaling-server: Missing Spreed-Signaling-Random header');

		header('HTTP/1.0 403 Forbidden');

		return;
	}

	if (empty($_SERVER['HTTP_SPREED_SIGNALING_CHECKSUM'])) {
		error_log('fake-signaling-server: Missing Spreed-Signaling-Checksum header');

		header('HTTP/1.0 403 Forbidden');

		return;
	}

	$random = $_SERVER['HTTP_SPREED_SIGNALING_RANDOM'];
	$checksum = $_SERVER['HTTP_SPREED_SIGNALING_CHECKSUM'];

	$data = file_get_contents('php://input');

	$hash = hash_hmac('sha256', $random . $data, 'the signaling secret');
	if (!hash_equals($hash, strtolower($checksum))) {
		error_log('fake-signaling-server: Checksum does not match');

		header('HTTP/1.0 403 Forbidden');

		return;
	}

	$receivedRequests = [];
	if (file_exists($receivedRequestsFile)) {
		$receivedRequests = json_decode(file_get_contents($receivedRequestsFile));
	}
	$receivedRequests[] = [
		'token' => $matches[1],
		'data' => $data,
	];
	file_put_contents($receivedRequestsFile, json_encode($receivedRequests));

	if (file_exists($receivedRequestsFile)) {
		$response = file_get_contents($nextResponseFile);
		unlink($nextResponseFile);
	} else {
		$response = 'No response stored';
	}

	header('X-Spreed-Signaling-Features: ' . implode(',', [
		'audio-video-permissions',
		'incall-all',
		'hello-v2',
		'switchto',
	]));

	echo $response ?: '{"type": "dialout","dialout": {"callid": "the-call-id"}}';
} elseif (preg_match('/\/fake\/requests/', $_SERVER['REQUEST_URI'])) {
	if (!file_exists($receivedRequestsFile)) {
		return;
	}

	$requests = file_get_contents($receivedRequestsFile);

	// Previous received requests are cleared.
	unlink($receivedRequestsFile);

	echo $requests;
} else {
	header('HTTP/1.0 404 Not Found');
}

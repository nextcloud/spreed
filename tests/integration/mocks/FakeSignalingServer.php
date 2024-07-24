<?php
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
		'federation',
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

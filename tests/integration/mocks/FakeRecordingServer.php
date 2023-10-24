<?php
/**
 * @copyright Copyright (c) 2023 Daniel Calviño Sánchez <danxuliu@gmail.com>
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

$receivedRequestsFile = sys_get_temp_dir() . '/fake-nextcloud-talk-recording-requests';

if (preg_match('/\/api\/v1\/welcome/', $_SERVER['REQUEST_URI'])) {
	echo json_encode(['version' => '0.1-fake']);
} elseif (preg_match('/\/api\/v1\/room\/([^\/]+)/', $_SERVER['REQUEST_URI'], $matches)) {
	if (empty($_SERVER['HTTP_TALK_RECORDING_RANDOM'])) {
		error_log('fake-recording-server: Missing Talk-Recording-Random header');

		header('HTTP/1.0 403 Forbidden');

		return;
	}

	if (empty($_SERVER['HTTP_TALK_RECORDING_CHECKSUM'])) {
		error_log('fake-recording-server: Missing Talk-Recording-Checksum header');

		header('HTTP/1.0 403 Forbidden');

		return;
	}

	$random = $_SERVER['HTTP_TALK_RECORDING_RANDOM'];
	$checksum = $_SERVER['HTTP_TALK_RECORDING_CHECKSUM'];

	$data = file_get_contents('php://input');

	$hash = hash_hmac('sha256', $random . $data, 'the recording secret');
	if (!hash_equals($hash, strtolower($checksum))) {
		error_log('fake-recording-server: Checksum does not match');

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
} elseif (preg_match('/\/fake\/requests/', $_SERVER['REQUEST_URI'])) {
	if (!file_exists($receivedRequestsFile)) {
		return;
	}

	$requests = file_get_contents($receivedRequestsFile);

	// Previous received requests are cleared.
	unlink($receivedRequestsFile);

	echo $requests;
} elseif (preg_match('/\/fake\/send-backend-request/', $_SERVER['REQUEST_URI'])) {
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $_SERVER['HTTP_BACKEND_URL']);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'OCS-APIRequest: true',
		'Talk-Recording-Random: ' . $_SERVER['HTTP_TALK_RECORDING_RANDOM'],
		'Talk-Recording-Checksum: ' . $_SERVER['HTTP_TALK_RECORDING_CHECKSUM'],
	]);
	curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$result = curl_exec($ch);
	$responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

	curl_close($ch);

	http_response_code($responseCode);
	echo $result;
} else {
	header('HTTP/1.0 404 Not Found');
}

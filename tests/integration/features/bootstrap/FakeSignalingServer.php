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

	$hash = hash_hmac('sha256', $random . $data, 'the secret');
	if (!hash_equals($hash, strtolower($checksum))) {
		error_log('fake-signaling-server: Checksum does not match');

		header('HTTP/1.0 403 Forbidden');

		return;
	}
	header('X-Spreed-Signaling-Features: ' . implode(',', [
		'audio-video-permissions',
		'incall-all',
		'hello-v2',
		'switchto',
	]));
} else {
	header('HTTP/1.0 404 Not Found');
}

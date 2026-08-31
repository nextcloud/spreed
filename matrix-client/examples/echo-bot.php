<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Minimal echo bot proving the library runs without Nextcloud:
 *   MATRIX_HOMESERVER=http://localhost:8008 MATRIX_USER=bot MATRIX_PASSWORD=secret php examples/echo-bot.php
 * Accepts every invite and echoes text messages back into the room.
 */

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\HttpFactory;
use Nextcloud\Matrix\Client;
use Nextcloud\Matrix\Http\Transport;

$homeserver = getenv('MATRIX_HOMESERVER') ?: 'http://localhost:8008';
$user = getenv('MATRIX_USER') ?: throw new RuntimeException('MATRIX_USER missing');
$password = getenv('MATRIX_PASSWORD') ?: throw new RuntimeException('MATRIX_PASSWORD missing');

$factory = new HttpFactory();
$client = new Client(new Transport($homeserver, new Guzzle(['timeout' => 40, 'http_errors' => false]), $factory, $factory));
$login = $client->loginWithPassword($user, $password, 'matrix-client-php echo bot');
$client = $client->withAccessToken($login->accessToken);
fwrite(STDERR, "Logged in as {$login->userId} (device {$login->deviceId})\n");

$since = null;
$filter = Client::defaultFilter(20);
while (true) {
	$batch = $client->sync($since, $filter, $since === null ? 0 : 30000);
	foreach ($batch->invited as $roomId => $invite) {
		fwrite(STDERR, "Joining $roomId (invited by {$invite->getInviter($login->userId)})\n");
		$client->join($roomId);
	}
	if ($since !== null) {
		foreach ($batch->joined as $roomId => $room) {
			foreach ($room->timeline as $event) {
				if ($event->type === 'm.room.message' && $event->sender !== $login->userId && $event->getMsgType() === 'm.text') {
					$client->sendMessage($roomId, Client::textContent('Echo: ' . $event->getBody()), 'echo-' . bin2hex(random_bytes(8)));
				}
			}
		}
	}
	$since = $batch->nextBatch;
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Tests\Integration;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\HttpFactory;
use Nextcloud\Matrix\Client;
use Nextcloud\Matrix\Discovery;
use Nextcloud\Matrix\Exception\ForbiddenException;
use Nextcloud\Matrix\Http\Transport;
use Nextcloud\Matrix\Model\RoomState;
use PHPUnit\Framework\TestCase;

/**
 * Runs against a real homeserver with open registration (see README.md).
 */
final class SynapseTest extends TestCase {
	private static string $homeserver;
	private static Client $alice;
	private static Client $bob;
	private static string $aliceId;
	private static string $bobId;

	public static function setUpBeforeClass(): void {
		$hs = getenv('MATRIX_HOMESERVER');
		if ($hs === false || $hs === '') {
			self::markTestSkipped('MATRIX_HOMESERVER not set');
		}
		self::$homeserver = rtrim($hs, '/');
		$factory = new HttpFactory();
		$anonymous = new Client(new Transport(self::$homeserver, new Guzzle(['timeout' => 40, 'http_errors' => false]), $factory, $factory));
		[self::$alice, self::$aliceId] = self::register($anonymous, 'alice');
		[self::$bob, self::$bobId] = self::register($anonymous, 'bob');
	}

	/** @return array{Client, string} */
	private static function register(Client $anonymous, string $prefix): array {
		$user = $prefix . '_' . bin2hex(random_bytes(4));
		$result = $anonymous->getTransport()->post(Client::PREFIX . '/register', [
			'username' => $user,
			'password' => 'pw-' . $user,
			'auth' => ['type' => 'm.login.dummy'],
			'initial_device_display_name' => 'matrix-client-php tests',
		]);
		return [$anonymous->withAccessToken((string)$result['access_token']), (string)$result['user_id']];
	}

	public function testDiscoveryAndVersions(): void {
		$factory = new HttpFactory();
		$discovery = new Discovery(new Guzzle(['timeout' => 20, 'http_errors' => false]), $factory);
		$versions = $discovery->validate(self::$homeserver);
		self::assertNotEmpty($versions['versions']);
		self::assertTrue(self::$alice->supportsSpecVersion($versions['versions'][0]));
	}

	public function testLoginWhoamiLogout(): void {
		$login = self::$alice->withAccessToken(null)->loginWithPassword(\Nextcloud\Matrix\Util\Identifier::localpart(self::$aliceId), 'pw-' . \Nextcloud\Matrix\Util\Identifier::localpart(self::$aliceId), 'test device');
		self::assertSame(self::$aliceId, $login->userId);
		$session = self::$alice->withAccessToken($login->accessToken);
		self::assertSame(self::$aliceId, $session->whoami()['user_id']);
		$session->logout();
		$this->expectException(\Nextcloud\Matrix\Exception\UnknownTokenException::class);
		$session->whoami();
	}

	public function testRoomLifecycleAndMessaging(): void {
		$roomId = self::$alice->createRoom(['name' => 'Integration', 'topic' => 'topic', 'invite' => [self::$bobId], 'preset' => 'private_chat']);
		self::assertStringStartsWith('!', $roomId);

		// Bob sees the invite in sync and joins
		$bobSync = self::$bob->sync(null, Client::defaultFilter());
		self::assertArrayHasKey($roomId, $bobSync->invited);
		self::assertSame(self::$aliceId, $bobSync->invited[$roomId]->getInviter(self::$bobId));
		self::assertSame($roomId, self::$bob->join($roomId));

		// Alice sends a formatted reply-less message, Bob replies with a relation
		$eventId = self::$alice->sendMessage($roomId, Client::textContent('Hello **Bob**', 'Hello <strong>Bob</strong>', mentionedUserIds: [self::$bobId]), 'it-' . bin2hex(random_bytes(6)));
		self::assertStringStartsWith('$', $eventId);
		$replyId = self::$bob->sendMessage($roomId, Client::textContent('Hi Alice', null, $eventId), 'it-' . bin2hex(random_bytes(6)));

		$aliceSync = self::$alice->sync(null, Client::defaultFilter());
		$room = $aliceSync->joined[$roomId];
		$state = new RoomState($roomId);
		$state->applyAll($room->getStateEvents());
		self::assertSame('Integration', $state->name);
		self::assertSame('topic', $state->topic);
		self::assertTrue($state->getPowerLevels()->isAdmin(self::$aliceId));
		self::assertFalse($state->getPowerLevels()->isModerator(self::$bobId));
		$bodies = [];
		foreach ($room->timeline as $event) {
			if ($event->type === 'm.room.message') {
				$bodies[$event->eventId] = [$event->getBody(), $event->getInReplyTo()];
			}
		}
		self::assertSame(['Hello **Bob**', null], $bodies[$eventId]);
		self::assertSame(['Hi Alice', $eventId], $bodies[$replyId]);

		// Paginate backwards
		$page = self::$alice->getMessages($roomId, $room->prevBatch, 'b', 50);
		self::assertNotEmpty($page->chunk);

		// Bob may not rename (PL 50 needed), Alice may
		try {
			self::$bob->sendStateEvent($roomId, 'm.room.name', ['name' => 'Nope']);
			self::fail('Bob should not be allowed to rename');
		} catch (ForbiddenException $e) {
			self::assertSame('M_FORBIDDEN', $e->getErrcode());
		}
		self::$alice->sendStateEvent($roomId, 'm.room.name', ['name' => 'Renamed']);
		self::assertSame('Renamed', self::$alice->getRoomState($roomId)->name);

		// Receipts, redaction, leave
		self::$bob->setReadMarker($roomId, $eventId);
		self::$alice->redact($roomId, $eventId, 'it-' . bin2hex(random_bytes(6)), 'cleanup');
		self::assertTrue(self::$alice->getEvent($roomId, $eventId)->isRedacted());
		self::$bob->leave($roomId);
		self::$bob->forget($roomId);
		$members = self::$alice->getRoomState($roomId)->getJoinedMembers();
		self::assertArrayHasKey(self::$aliceId, $members);
		self::assertArrayNotHasKey(self::$bobId, $members);
	}

	public function testMediaRoundTrip(): void {
		$factory = new HttpFactory();
		$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
		$mxc = self::$alice->uploadMedia($factory->createStream($png), 'image/png', 'dot.png');
		$response = self::$alice->downloadMedia($mxc);
		self::assertSame($png, (string)$response->getBody());
		self::assertStringStartsWith('image/png', $response->getHeaderLine('Content-Type'));
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Tests\Unit;

use Nextcloud\Matrix\Client;
use Nextcloud\Matrix\Http\Transport;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase {
	private FakeHttpClient $http;
	private Client $client;

	protected function setUp(): void {
		$this->http = new FakeHttpClient();
		$factory = new Psr17Factory();
		$this->client = new Client((new Transport('https://hs', $this->http, $factory, $factory))->withAccessToken('tok'));
	}

	public function testLoginWithPasswordDoesNotSendStaleToken(): void {
		$this->http->queueJson(200, ['user_id' => '@a:hs', 'access_token' => 'new', 'device_id' => 'DEV']);
		$result = $this->client->loginWithPassword('a', 'pw', 'Talk', 'OLDDEV');
		self::assertSame('@a:hs', $result->userId);
		self::assertSame('new', $result->accessToken);
		self::assertSame('DEV', $result->deviceId);
		$request = $this->http->lastRequest();
		self::assertFalse($request->hasHeader('Authorization'));
		$body = $this->http->lastBody();
		self::assertSame('m.login.password', $body['type']);
		self::assertSame(['type' => 'm.id.user', 'user' => 'a'], $body['identifier']);
		self::assertSame('OLDDEV', $body['device_id']);
	}

	public function testSyncParsesBatch(): void {
		$this->http->queueJson(200, json_decode((string)file_get_contents(__DIR__ . '/../fixtures/sync.json'), true));
		$batch = $this->client->sync('s0', 'f1', 5000);
		self::assertSame('s1', $batch->nextBatch);
		self::assertStringContainsString('since=s0&timeout=5000&set_presence=offline&filter=f1', (string)$this->http->lastRequest()->getUri());
		self::assertArrayHasKey('!room:hs', $batch->joined);
		self::assertArrayHasKey('!invite:hs', $batch->invited);
		self::assertArrayHasKey('!left:hs', $batch->left);
		self::assertSame(['!room:hs' => ['@bob:hs']], $batch->getDirectRooms());
	}

	public function testTextContentBuildsRelations(): void {
		$plain = Client::textContent('hi');
		self::assertSame(['msgtype' => 'm.text', 'body' => 'hi'], $plain);

		$reply = Client::textContent('hi', '<b>hi</b>', '$parent');
		self::assertSame('org.matrix.custom.html', $reply['format']);
		self::assertSame(['m.in_reply_to' => ['event_id' => '$parent']], $reply['m.relates_to']);

		$thread = Client::textContent('hi', null, null, '$root', '$last', ['@bob:hs'], true);
		self::assertSame('m.thread', $thread['m.relates_to']['rel_type']);
		self::assertSame('$root', $thread['m.relates_to']['event_id']);
		self::assertTrue($thread['m.relates_to']['is_falling_back']);
		self::assertSame('$last', $thread['m.relates_to']['m.in_reply_to']['event_id']);
		self::assertSame(['user_ids' => ['@bob:hs'], 'room' => true], $thread['m.mentions']);
	}

	public function testSendMessageUsesTxnIdPath(): void {
		$this->http->queueJson(200, ['event_id' => '$new']);
		self::assertSame('$new', $this->client->sendMessage('!r:hs', ['msgtype' => 'm.text', 'body' => 'x'], 'nc-1'));
		self::assertSame('https://hs/_matrix/client/v3/rooms/%21r%3Ahs/send/m.room.message/nc-1', (string)$this->http->lastRequest()->getUri());
		self::assertSame('PUT', $this->http->lastRequest()->getMethod());
	}

	public function testJoinWithVia(): void {
		$this->http->queueJson(200, ['room_id' => '!r:hs']);
		self::assertSame('!r:hs', $this->client->join('#alias:hs', ['a.org']));
		self::assertSame('https://hs/_matrix/client/v3/join/%23alias%3Ahs?via=a.org', (string)$this->http->lastRequest()->getUri());
	}

	public function testMediaDownloadPrefersAuthenticatedEndpoint(): void {
		$this->client->setVersions(['versions' => ['v1.10', 'v1.11']]);
		$this->http->queueRaw(200, 'PNG', ['Content-Type' => 'image/png']);
		$response = $this->client->downloadMedia('mxc://origin.org/abc');
		self::assertSame('PNG', (string)$response->getBody());
		self::assertSame('https://hs/_matrix/client/v1/media/download/origin.org/abc?timeout_ms=20000', (string)$this->http->lastRequest()->getUri());

		$this->client->setVersions(['versions' => ['v1.5']]);
		$this->http->queueRaw(200, 'PNG');
		$this->client->downloadMedia('mxc://origin.org/abc');
		self::assertStringStartsWith('https://hs/_matrix/media/v3/download/origin.org/abc', (string)$this->http->lastRequest()->getUri());
	}

	public function testGetRoomStateAggregates(): void {
		$this->http->queueJson(200, [
			['type' => 'm.room.create', 'state_key' => '', 'sender' => '@a:hs', 'event_id' => '$1', 'origin_server_ts' => 1, 'content' => ['creator' => '@a:hs', 'room_version' => '10']],
			['type' => 'm.room.name', 'state_key' => '', 'sender' => '@a:hs', 'event_id' => '$2', 'origin_server_ts' => 2, 'content' => ['name' => 'Room']],
			['type' => 'm.room.member', 'state_key' => '@a:hs', 'sender' => '@a:hs', 'event_id' => '$3', 'origin_server_ts' => 3, 'content' => ['membership' => 'join', 'displayname' => 'A']],
		]);
		$state = $this->client->getRoomState('!r:hs');
		self::assertSame('Room', $state->name);
		self::assertSame('10', $state->roomVersion);
		self::assertSame('@a:hs', $state->creator);
		self::assertCount(1, $state->getJoinedMembers());
		self::assertTrue($state->getPowerLevels()->isAdmin('@a:hs'));
	}
}

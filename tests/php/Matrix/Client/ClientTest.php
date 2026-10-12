<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Client;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Model\Event;
use OCA\Talk\Matrix\Client\Transport;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Test\TestCase;

class ClientTest extends TestCase {
	/** @var list<ResponseInterface> */
	private array $responses = [];
	/** @var list<string> */
	private array $paths = [];
	/** @var list<string> */
	private array $queries = [];
	/** @var list<string> */
	private array $methods = [];
	private Client $client;

	protected function setUp(): void {
		parent::setUp();
		$http = new class($this) implements ClientInterface {
			public function __construct(
				private readonly ClientTest $test,
			) {
			}

			public function sendRequest(RequestInterface $request): ResponseInterface {
				return $this->test->respond($request);
			}
		};
		$factory = new HttpFactory();
		$this->client = new Client(new Transport('https://matrix.example.org', $http, $factory, $factory));
	}

	public function respond(RequestInterface $request): ResponseInterface {
		$this->paths[] = $request->getUri()->getPath();
		$this->queries[] = $request->getUri()->getQuery();
		$this->methods[] = $request->getMethod();
		return array_shift($this->responses) ?? new Response(404);
	}

	public function testDownloadMedia(): void {
		$this->responses[] = new Response(200, ['Content-Type' => 'image/png'], 'png');

		self::assertSame('png', (string)$this->client->downloadMedia('mxc://example.org/abc')->getBody());
		self::assertSame(['/_matrix/client/v1/media/download/example.org/abc'], $this->paths);
	}

	public function testDownloadMediaFromOlderHomeserver(): void {
		$this->responses[] = new Response(404, ['Content-Type' => 'application/json'], '{"errcode":"M_UNRECOGNIZED","error":"Unrecognized request"}');
		$this->responses[] = new Response(200, ['Content-Type' => 'image/png'], 'png');

		self::assertSame('png', (string)$this->client->downloadMedia('mxc://example.org/abc')->getBody());
		self::assertSame(['/_matrix/client/v1/media/download/example.org/abc', '/_matrix/media/v3/download/example.org/abc'], $this->paths);
	}

	public function testUploadAndQueryKeys(): void {
		$this->responses[] = new Response(200, ['Content-Type' => 'application/json'], '{"one_time_key_counts":{"signed_curve25519":50}}');
		$this->responses[] = new Response(200, ['Content-Type' => 'application/json'], json_encode([
			'device_keys' => ['@bob:hs' => ['BOB' => ['device_id' => 'BOB'], 'INVALID' => 'x']],
			'failures' => [],
		]));

		self::assertSame(['signed_curve25519' => 50], $this->client->uploadKeys(['device_id' => 'DEV'], [], []));
		self::assertSame(['@bob:hs' => ['BOB' => ['device_id' => 'BOB']]], $this->client->queryKeys(['@bob:hs']));
		self::assertSame(['/_matrix/client/v3/keys/upload', '/_matrix/client/v3/keys/query'], $this->paths);
	}

	public function testClaimKeys(): void {
		$this->responses[] = new Response(200, ['Content-Type' => 'application/json'], json_encode([
			'one_time_keys' => ['@bob:hs' => ['BOB' => ['signed_curve25519:AAAAAQ' => ['key' => 'k']], 'INVALID' => 'x']],
			'failures' => [],
		]));
		$this->responses[] = new Response(200, ['Content-Type' => 'application/json'], '{"device_keys":{"@carol:hs":{}}}');

		self::assertSame(['@bob:hs' => ['BOB' => ['signed_curve25519:AAAAAQ' => ['key' => 'k']]]], $this->client->claimKeys(['@bob:hs' => ['BOB' => 'signed_curve25519']]));
		self::assertSame(['@carol:hs' => []], $this->client->queryKeys(['@carol:hs', '@unreachable:other']), 'Users without devices are returned, users that could not be queried are not');
		self::assertSame(['/_matrix/client/v3/keys/claim', '/_matrix/client/v3/keys/query'], $this->paths);
	}

	public function testKeyBackup(): void {
		$this->responses[] = new Response(200, ['Content-Type' => 'application/json'], '{"key":"KEY"}');
		$this->responses[] = new Response(200, ['Content-Type' => 'application/json'], '{"rooms":{"!room:hs":{"sessions":{"S1":{"first_message_index":0},"S2":"invalid"}}}}');
		$this->responses[] = new Response(200, ['Content-Type' => 'application/json'], '{"first_message_index":3}');

		self::assertSame(['key' => 'KEY'], $this->client->getAccountData('@alice:hs', 'm.secret_storage.default_key'));
		self::assertSame(['!room:hs' => ['S1' => ['first_message_index' => 0]]], $this->client->getRoomKeys('1'));
		self::assertSame(['!room:hs' => ['S2' => ['first_message_index' => 3]]], $this->client->getRoomKeys('1', '!room:hs', 'S2'));
		self::assertSame([
			'/_matrix/client/v3/user/%40alice%3Ahs/account_data/m.secret_storage.default_key',
			'/_matrix/client/v3/room_keys/keys',
			'/_matrix/client/v3/room_keys/keys/%21room%3Ahs/S2',
		], $this->paths);
		self::assertSame(['', 'version=1', 'version=1'], $this->queries);
	}

	public function testPushRules(): void {
		$this->responses[] = new Response(200, ['Content-Type' => 'application/json'], '{}');
		$this->responses[] = new Response(200, ['Content-Type' => 'application/json'], '{}');

		$this->client->setPushRule('room', '!room:example.org', ['actions' => []]);
		$this->client->deletePushRule('override', '!room:example.org');

		self::assertSame([
			'/_matrix/client/v3/pushrules/global/room/%21room%3Aexample.org',
			'/_matrix/client/v3/pushrules/global/override/%21room%3Aexample.org',
		], $this->paths);
		self::assertSame(['PUT', 'DELETE'], $this->methods);
	}

	public function testGetMessages(): void {
		$this->responses[] = new Response(200, ['Content-Type' => 'application/json'], json_encode([
			'chunk' => [
				['event_id' => '$2', 'type' => 'm.room.message', 'sender' => '@bob:example.org', 'content' => ['body' => 'Two']],
				['event_id' => '$1', 'type' => 'm.room.message', 'sender' => '@bob:example.org', 'content' => ['body' => 'One']],
			],
			'start' => 't2',
			'end' => 't1',
		]));
		$this->responses[] = new Response(200, ['Content-Type' => 'application/json'], '{"chunk":[],"start":"t1"}');

		$page = $this->client->getMessages('!room:example.org', 't2', 50);
		self::assertSame(['$2', '$1'], array_map(static fn (Event $event): string => $event->eventId, $page['events']));
		self::assertSame('t1', $page['end']);
		self::assertSame(['/_matrix/client/v3/rooms/%21room%3Aexample.org/messages'], $this->paths);
		self::assertSame(['from=t2&dir=b&limit=50'], $this->queries);

		self::assertSame(['events' => [], 'end' => null], $this->client->getMessages('!room:example.org', 't1', 50));
	}

	public function testDownloadThumbnail(): void {
		$this->responses[] = new Response(404, ['Content-Type' => 'application/json'], '{"errcode":"M_UNRECOGNIZED","error":"Unrecognized request"}');
		$this->responses[] = new Response(200, ['Content-Type' => 'image/png'], 'png');

		self::assertSame('png', (string)$this->client->downloadThumbnail('mxc://example.org/abc', 640, false)->getBody());
		self::assertSame(['/_matrix/client/v1/media/thumbnail/example.org/abc', '/_matrix/media/v3/thumbnail/example.org/abc'], $this->paths);
		self::assertSame(['width=640&height=640&method=scale', 'width=640&height=640&method=scale'], $this->queries);
	}

	public function testDownloadMissingMedia(): void {
		$this->responses[] = new Response(404, ['Content-Type' => 'application/json'], '{"errcode":"M_NOT_FOUND","error":"Not found"}');

		try {
			$this->client->downloadMedia('mxc://example.org/abc');
			self::fail('Expected exception');
		} catch (MatrixException $e) {
			self::assertSame('M_NOT_FOUND', $e->getErrcode());
		}
		self::assertCount(1, $this->paths);
	}

	public function testDownloadInvalidContentUri(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->client->downloadMedia('mxc://example.org/../../admin');
	}
}

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

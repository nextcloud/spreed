<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Tests\Unit;

use Nextcloud\Matrix\Exception\ForbiddenException;
use Nextcloud\Matrix\Exception\MatrixException;
use Nextcloud\Matrix\Exception\NotFoundException;
use Nextcloud\Matrix\Exception\RateLimitedException;
use Nextcloud\Matrix\Exception\TransportException;
use Nextcloud\Matrix\Exception\UnknownTokenException;
use Nextcloud\Matrix\Http\Transport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;

final class TransportTest extends TestCase {
	private FakeHttpClient $http;
	private Transport $transport;
	/** @var list<int> */
	private array $sleeps = [];

	protected function setUp(): void {
		$this->http = new FakeHttpClient();
		$factory = new Psr17Factory();
		$this->transport = (new Transport('https://hs.example.org/', $this->http, $factory, $factory))->withAccessToken('tok');
		$this->transport->setSleeper(function (int $ms): void {
			$this->sleeps[] = $ms;
		});
	}

	public function testBuildsUrlAuthAndQuery(): void {
		$this->http->queueJson(200, ['ok' => true]);
		$result = $this->transport->get('/_matrix/client/v3/sync', ['since' => 's1', 'timeout' => 0, 'skip' => null, 'via' => ['a.org', 'b.org'], 'full_state' => true]);
		self::assertSame(['ok' => true], $result);
		$request = $this->http->lastRequest();
		self::assertSame('https://hs.example.org/_matrix/client/v3/sync?since=s1&timeout=0&via=a.org&via=b.org&full_state=true', (string)$request->getUri());
		self::assertSame('Bearer tok', $request->getHeaderLine('Authorization'));
	}

	public function testPutSendsJsonBody(): void {
		$this->http->queueJson(200, ['event_id' => '$e']);
		$this->transport->put('/x', ['body' => 'hé', 'n' => 1]);
		self::assertSame('application/json', $this->http->lastRequest()->getHeaderLine('Content-Type'));
		self::assertSame(['body' => 'hé', 'n' => 1], $this->http->lastBody());
	}

	public function testPostWithoutBodySendsEmptyObject(): void {
		$this->http->queueJson(200, []);
		$this->transport->post('/logout');
		self::assertSame('{}', (string)$this->http->lastRequest()->getBody());
	}

	/** @dataProvider errorProvider */
	public function testErrorMapping(int $status, string $errcode, string $class): void {
		$this->http->queueJson($status, ['errcode' => $errcode, 'error' => 'boom']);
		$this->transport->setMaxRetries(0);
		try {
			$this->transport->get('/x');
			self::fail('expected exception');
		} catch (MatrixException $e) {
			self::assertInstanceOf($class, $e);
			self::assertSame($status, $e->getHttpStatus());
			self::assertSame($errcode, $e->getErrcode());
			self::assertSame('boom', $e->getMessage());
		}
	}

	public static function errorProvider(): array {
		return [
			[401, 'M_UNKNOWN_TOKEN', UnknownTokenException::class],
			[403, 'M_FORBIDDEN', ForbiddenException::class],
			[404, 'M_NOT_FOUND', NotFoundException::class],
			[429, 'M_LIMIT_EXCEEDED', RateLimitedException::class],
			[400, 'M_BAD_JSON', MatrixException::class],
		];
	}

	public function testRetriesOnRateLimitHonouringRetryAfter(): void {
		$this->http->queueJson(429, ['errcode' => 'M_LIMIT_EXCEEDED', 'error' => 'slow down', 'retry_after_ms' => 1234]);
		$this->http->queueJson(200, ['fine' => 1]);
		self::assertSame(['fine' => 1], $this->transport->get('/x'));
		self::assertSame([1234], $this->sleeps);
		self::assertCount(2, $this->http->requests);
	}

	public function testRetriesOnServerErrorThenGivesUp(): void {
		for ($i = 0; $i < 4; $i++) {
			$this->http->queueJson(502, ['errcode' => 'M_UNKNOWN', 'error' => 'gateway']);
		}
		$this->expectException(MatrixException::class);
		try {
			$this->transport->get('/x');
		} finally {
			self::assertCount(4, $this->http->requests);
		}
	}

	public function testNetworkErrorBecomesTransportException(): void {
		$this->transport->setMaxRetries(1);
		$this->http->queueException(new NetworkException(new Request('GET', '/')));
		$this->http->queueException(new NetworkException(new Request('GET', '/')));
		$this->expectException(TransportException::class);
		$this->transport->get('/x');
	}

	public function testNonJsonErrorBecomesTransportException(): void {
		$this->transport->setMaxRetries(0);
		$this->http->queueRaw(502, '<html>bad gateway</html>');
		$this->expectException(TransportException::class);
		$this->transport->get('/x');
	}
}

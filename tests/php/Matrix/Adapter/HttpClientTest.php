<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Adapter;

use GuzzleHttp\Psr7\HttpFactory;
use OCA\Talk\Matrix\Adapter\HttpClient;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class HttpClientTest extends TestCase {
	private IClient&MockObject $client;
	private HttpClient $http;

	protected function setUp(): void {
		parent::setUp();
		$this->client = $this->createMock(IClient::class);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($this->client);
		$this->http = new HttpClient($clientService, 10);
	}

	private function response(string $body): IResponse&MockObject {
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, $body);
		rewind($stream);
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getHeaders')->willReturn(['Content-Type' => ['image/png']]);
		$response->method('getBody')->willReturn($stream);
		return $response;
	}

	public function testStreamsUpload(): void {
		$factory = new HttpFactory();
		$body = $factory->createStream('cat');
		$this->client->expects(self::once())
			->method('post')
			->with('https://matrix.example.org/upload', self::callback(static fn (array $options): bool => $options['body'] === $body && $options['stream'] === true && $options['timeout'] === 10))
			->willReturn($this->response('{}'));

		$this->http->sendRequest($factory->createRequest('POST', 'https://matrix.example.org/upload')->withBody($body));
	}

	public function testStreamsDownload(): void {
		$factory = new HttpFactory();
		$this->client->expects(self::once())
			->method('get')
			->with('https://matrix.example.org/download', self::callback(static fn (array $options): bool => !isset($options['body']) && $options['stream'] === true))
			->willReturn($this->response('png'));

		$response = $this->http->sendRequest($factory->createRequest('GET', 'https://matrix.example.org/download'));

		self::assertSame(['image/png'], $response->getHeader('Content-Type'));
		self::assertIsResource($response->getBody()->detach());
	}
}

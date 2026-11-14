<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Signaling;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use OCA\Talk\Config;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Signaling\BackendNotifier;
use OCA\Talk\Signaling\Manager;
use OCA\Talk\Signaling\RoomPropertiesHelper;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class BackendNotifierTest extends TestCase {
	private LoggerInterface&MockObject $logger;
	private BackendNotifier $backendNotifier;
	private Config&MockObject $config;
	private IAppConfig&MockObject $appConfig;
	private IClientService&MockObject $clientService;
	private IClient&MockObject $client;
	private ISecureRandom&MockObject $secureRandom;
	private Manager&MockObject $signalingManager;
	private ParticipantService&MockObject $participantService;
	private IURLGenerator&MockObject $urlGenerator;
	private RoomPropertiesHelper&MockObject $roomPropertiesHelper;

	/** @var array{server: string, verify?: bool}[] */
	private array $signalingServers = [];

	public function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->config = $this->createMock(Config::class);
		$this->config->method('getSignalingMode')->willReturn(Config::SIGNALING_EXTERNAL);
		$this->config->method('getSignalingServers')
			->willReturnCallback(fn (): array => $this->signalingServers);
		$this->config->method('getSignalingSecret')->willReturn('secret');
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->client = $this->createMock(IClient::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->clientService->method('newClient')->willReturn($this->client);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->secureRandom->method('generate')->willReturn(str_repeat('a', 64));
		$this->signalingManager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->urlGenerator->method('getAbsoluteURL')->willReturn('https://cloud.example/');
		$this->roomPropertiesHelper = $this->createMock(RoomPropertiesHelper::class);

		$this->backendNotifier = new BackendNotifier(
			$this->config,
			$this->appConfig,
			$this->logger,
			$this->clientService,
			$this->secureRandom,
			$this->signalingManager,
			$this->participantService,
			$this->urlGenerator,
			$this->roomPropertiesHelper,
		);
	}

	private function configureSignalingServer(): void {
		$this->signalingServers = [['server' => 'https://signaling.example', 'verify' => true]];
	}

	private function createRoom(): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getToken')->willReturn('token');
		return $room;
	}

	public static function dataSendRoomMessageLog(): array {
		return [
			'chat message' => [
				['type' => 'chat', 'chat' => ['refresh' => true, 'comment' => ['id' => 42, 'actorDisplayName' => 'Alice', 'message' => 'Secret', 'parent' => ['message' => 'Secret parent']]]],
				['type' => 'chat', 'chat' => ['refresh' => true, 'comment' => '[redacted #42]']],
			],
			'chat refresh' => [
				['type' => 'chat', 'chat' => ['refresh' => true]],
				['type' => 'chat', 'chat' => ['refresh' => true]],
			],
			'recording' => [
				['type' => 'recording', 'recording' => ['status' => 1]],
				['type' => 'recording', 'recording' => ['status' => 1]],
			],
		];
	}

	#[DataProvider('dataSendRoomMessageLog')]
	public function testSendRoomMessageLog(array $message, array $expected): void {
		$this->logger->expects($this->once())
			->method('debug')
			->with('Send room message: {token} {message} ({duration})', $this->callback(function (array $context) use ($expected): bool {
				$this->assertSame($expected, $context['message']);
				return true;
			}));

		$this->backendNotifier->sendRoomMessage($this->createRoom(), $message);
	}

	public function testRequestSetsConnectAndTotalTimeout(): void {
		$this->configureSignalingServer();
		$this->signalingManager->method('isCompatibleSignalingServer')->willReturn(true);

		$this->client->expects($this->once())
			->method('post')
			->with(
				'https://signaling.example/api/v1/room/token',
				$this->callback(function (array $params): bool {
					$this->assertSame(5, $params['connect_timeout']);
					$this->assertSame(10, $params['timeout']);
					return true;
				}),
			)
			->willReturn($this->createMock(IResponse::class));

		$this->backendNotifier->sendRoomMessage($this->createRoom(), ['type' => 'chat', 'chat' => ['refresh' => true]]);
	}

	public function testRequestRetriesTransportFailures(): void {
		$this->configureSignalingServer();
		$this->client->expects($this->exactly(3))
			->method('post')
			->willThrowException(new ConnectException(
				'cURL error 7: Failed to connect',
				new Request('POST', 'https://signaling.example/api/v1/room/token'),
			));
		$this->logger->expects($this->exactly(3))
			->method('error');

		$this->expectException(ConnectException::class);
		$this->backendNotifier->sendRoomMessage($this->createRoom(), ['type' => 'chat', 'chat' => ['refresh' => true]]);
	}

	public function testRequestDoesNotRetryClientErrors(): void {
		$this->configureSignalingServer();
		$this->client->expects($this->once())
			->method('post')
			->willThrowException(new ClientException(
				'403 Forbidden',
				new Request('POST', 'https://signaling.example/api/v1/room/token'),
				new GuzzleResponse(403),
			));
		$this->logger->expects($this->once())
			->method('error')
			->with(
				'Signaling server rejected the request',
				$this->callback(static fn (array $context): bool => $context['status'] === 403),
			);

		$this->expectException(ClientException::class);
		$this->backendNotifier->sendRoomMessage($this->createRoom(), ['type' => 'chat', 'chat' => ['refresh' => true]]);
	}
}

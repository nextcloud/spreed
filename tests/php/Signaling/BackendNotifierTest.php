<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Signaling;

use OCA\Talk\Config;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Signaling\BackendNotifier;
use OCA\Talk\Signaling\Manager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Http\Client\IClientService;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class BackendNotifierTest extends TestCase {
	private LoggerInterface&MockObject $logger;
	private BackendNotifier $backendNotifier;

	public function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(LoggerInterface::class);
		$config = $this->createMock(Config::class);
		$config->method('getSignalingMode')->willReturn(Config::SIGNALING_INTERNAL);
		$this->backendNotifier = new BackendNotifier(
			$config,
			$this->createMock(IAppConfig::class),
			$this->logger,
			$this->createMock(IClientService::class),
			$this->createMock(ISecureRandom::class),
			$this->createMock(Manager::class),
			$this->createMock(ParticipantService::class),
			$this->createMock(IURLGenerator::class),
		);
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
		$room = $this->createMock(Room::class);
		$room->method('getToken')->willReturn('token');

		$this->logger->expects($this->once())
			->method('debug')
			->with('Send room message: {token} {message} ({duration})', $this->callback(function (array $context) use ($expected): bool {
				$this->assertSame($expected, $context['message']);
				return true;
			}));

		$this->backendNotifier->sendRoomMessage($room, $message);
	}
}

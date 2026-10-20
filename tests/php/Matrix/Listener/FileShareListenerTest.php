<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Listener;

use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Matrix\Listener\FileShareListener;
use OCA\Talk\Matrix\Service\SendException;
use OCA\Talk\Matrix\Service\SendService;
use OCA\Talk\Room;
use OCP\Comments\IComment;
use OCP\Files\File;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class FileShareListenerTest extends TestCase {
	private SendService&MockObject $sendService;
	private IShareManager&MockObject $shareManager;
	private LoggerInterface&MockObject $logger;
	private FileShareListener $listener;

	protected function setUp(): void {
		parent::setUp();
		$this->sendService = $this->createMock(SendService::class);
		$this->shareManager = $this->createMock(IShareManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->listener = new FileShareListener($this->sendService, $this->shareManager, $this->logger);
	}

	private function event(string $objectType, array $message): SystemMessageSentEvent {
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn($objectType);
		$comment = $this->createMock(IComment::class);
		$comment->method('getMessage')->willReturn(json_encode($message));
		return new SystemMessageSentEvent($room, $comment);
	}

	public function testFileShare(): void {
		$file = $this->createMock(File::class);
		$share = $this->createMock(IShare::class);
		$share->method('getNode')->willReturn($file);
		$this->shareManager->method('getShareById')->with('ocRoomShare:42')->willReturn($share);
		$event = $this->event(Room::OBJECT_TYPE_MATRIX, ['message' => 'file_shared', 'parameters' => ['share' => '42', 'metaData' => ['caption' => 'Look']]]);
		$this->sendService->expects(self::once())
			->method('sendFile')
			->with($event->getRoom(), $event->getComment(), $file, 'Look')
			->willThrowException(new SendException('matrix', 502));
		$this->logger->expects(self::once())->method('warning');

		$this->listener->handle($event);
	}

	public static function dataIgnored(): array {
		return [
			'other conversation' => ['', ['message' => 'file_shared', 'parameters' => ['share' => '42']]],
			'other system message' => [Room::OBJECT_TYPE_MATRIX, ['message' => 'conversation_renamed', 'parameters' => []]],
		];
	}

	#[DataProvider('dataIgnored')]
	public function testIgnored(string $objectType, array $message): void {
		$this->shareManager->expects(self::never())->method('getShareById');
		$this->sendService->expects(self::never())->method('sendFile');

		$this->listener->handle($this->event($objectType, $message));
	}
}

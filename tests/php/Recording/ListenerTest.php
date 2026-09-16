<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Recording;

use OCA\Talk\Recording\Listener;
use OCA\Talk\Service\ConsentService;
use OCA\Talk\Service\RecordingService;
use OCP\Activity\IManager as IActivityManager;
use OCP\Files\Events\Node\BeforeNodeCreatedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Folder;
use OCP\Files\Node;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ListenerTest extends TestCase {
	protected RecordingService&MockObject $recordingService;
	protected ConsentService&MockObject $consentService;
	protected IActivityManager&MockObject $activityManager;
	protected LoggerInterface&MockObject $logger;
	protected Listener $listener;

	public function setUp(): void {
		parent::setUp();

		$this->recordingService = $this->createMock(RecordingService::class);
		$this->consentService = $this->createMock(ConsentService::class);
		$this->activityManager = $this->createMock(IActivityManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new Listener(
			$this->recordingService,
			$this->consentService,
			$this->activityManager,
			$this->logger,
		);
	}

	protected function nodeInFolder(string $folderName, string $nodeName): Node&MockObject {
		$folder = $this->createMock(Folder::class);
		$folder->method('getName')->willReturn($folderName);

		$node = $this->createMock(Node::class);
		$node->method('getParent')->willReturn($folder);
		$node->method('getName')->willReturn($nodeName);
		return $node;
	}

	public function testBeforeNodeCreatedSetsCurrentUserIdForPendingRecordingUpload(): void {
		$node = $this->nodeInFolder('token123', 'recording.mp4');

		$this->recordingService->method('getRecordingUploadOwner')
			->with('token123', 'recording.mp4')
			->willReturn('user1');

		$this->activityManager->expects($this->once())
			->method('setCurrentUserId')
			->with('user1');

		$this->listener->handle(new BeforeNodeCreatedEvent($node));
	}

	public function testBeforeNodeCreatedDoesNothingForUnrelatedNode(): void {
		$node = $this->nodeInFolder('somefolder', 'document.txt');

		$this->recordingService->method('getRecordingUploadOwner')
			->with('somefolder', 'document.txt')
			->willReturn(null);

		$this->activityManager->expects($this->never())->method('setCurrentUserId');

		$this->listener->handle(new BeforeNodeCreatedEvent($node));
	}

	public function testNodeWrittenResetsCurrentUserIdAfterMatchingBeforeNodeCreated(): void {
		$node = $this->nodeInFolder('token123', 'recording.mp4');

		$this->recordingService->method('getRecordingUploadOwner')
			->with('token123', 'recording.mp4')
			->willReturn('user1');

		$calls = [];
		$this->activityManager->expects($this->exactly(2))
			->method('setCurrentUserId')
			->willReturnCallback(function (?string $userId) use (&$calls): void {
				$calls[] = $userId;
			});

		$this->listener->handle(new BeforeNodeCreatedEvent($node));
		$this->listener->handle(new NodeWrittenEvent($node));

		$this->assertSame(['user1', null], $calls);
	}

	public function testNodeWrittenWithoutMatchingBeforeNodeCreatedDoesNotResetCurrentUserId(): void {
		// A plain update, e.g. an existing file being overwritten, only fires
		// NodeWrittenEvent, without a BeforeNodeCreatedEvent before it.
		$node = $this->nodeInFolder('token123', 'recording.mp4');

		$this->recordingService->method('getRecordingUploadOwner')
			->with('token123', 'recording.mp4')
			->willReturn('user1');

		$this->activityManager->expects($this->never())->method('setCurrentUserId');

		$this->listener->handle(new NodeWrittenEvent($node));
	}

	public function testNodeWrittenForUnrelatedNodeDoesNothing(): void {
		$node = $this->nodeInFolder('somefolder', 'document.txt');

		$this->recordingService->method('getRecordingUploadOwner')
			->with('somefolder', 'document.txt')
			->willReturn(null);

		$this->activityManager->expects($this->never())->method('setCurrentUserId');

		$this->listener->handle(new BeforeNodeCreatedEvent($node));
		$this->listener->handle(new NodeWrittenEvent($node));
	}
}

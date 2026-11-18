<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Controller\UploadsWithoutStorageController;
use OCA\Talk\Exceptions\RoomProperty\UploadsWithoutStorageException;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomFormatter;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\UploadsWithoutStorageService;
use OCP\AppFramework\Http;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class UploadsWithoutStorageControllerTest extends TestCase {
	protected RoomService&MockObject $roomService;
	protected RoomFormatter&MockObject $roomFormatter;
	protected UploadsWithoutStorageService&MockObject $uploadsWithoutStorageService;
	protected Room&MockObject $room;
	protected Participant&MockObject $participant;
	protected UploadsWithoutStorageController $controller;

	#[\Override]
	public function setUp(): void {
		parent::setUp();

		$this->roomService = $this->createMock(RoomService::class);
		$this->roomFormatter = $this->createMock(RoomFormatter::class);
		$this->uploadsWithoutStorageService = $this->createMock(UploadsWithoutStorageService::class);
		$this->roomFormatter->method('formatRoom')->willReturn(['token' => 'abcdef']);

		$this->controller = new UploadsWithoutStorageController(
			'spreed',
			$this->createMock(IRequest::class),
			$this->roomService,
			$this->roomFormatter,
			$this->uploadsWithoutStorageService,
		);

		$this->participant = $this->createMock(Participant::class);
		$this->room = $this->createMock(Room::class);
		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($this->participant);
	}

	public function testAllow(): void {
		$this->uploadsWithoutStorageService->expects($this->once())
			->method('allowUploads')
			->with($this->participant, $this->room);

		$response = $this->controller->setUploadsWithoutStorage(true);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['token' => 'abcdef'], $response->getData());
	}

	public function testAllowRefused(): void {
		$this->uploadsWithoutStorageService->method('allowUploads')
			->willThrowException(new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_STORAGE));

		$response = $this->controller->setUploadsWithoutStorage(true);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'storage'], $response->getData());
	}

	public function testStop(): void {
		$this->uploadsWithoutStorageService->expects($this->never())->method('allowUploads');
		$this->roomService->expects($this->once())->method('setGuestsFolderId')->with($this->room, 0);

		$response = $this->controller->setUploadsWithoutStorage(false);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testGetGuestsFolder(): void {
		$this->uploadsWithoutStorageService->method('getGuestsFolderLocation')
			->willReturn(['owner' => 'Alice', 'path' => 'Talk/Lorem-abcdef/Guests']);

		$response = $this->controller->getUploadsWithoutStorage();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['owner' => 'Alice', 'path' => 'Talk/Lorem-abcdef/Guests'], $response->getData());
	}

	public function testGetGuestsFolderWhenOff(): void {
		$this->uploadsWithoutStorageService->method('getGuestsFolderLocation')
			->willThrowException(new NotFoundException());

		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller->getUploadsWithoutStorage()->getStatus());
	}
}

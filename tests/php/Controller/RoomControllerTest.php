<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Controller\RoomController;
use OCA\Talk\Matrix\Service\SendException;
use OCA\Talk\Matrix\Service\SendService;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Http;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class RoomControllerTest extends TestCase {
	private ParticipantService&MockObject $participantService;
	private SendService&MockObject $sendService;
	private Room&MockObject $room;
	private Participant&MockObject $participant;
	private RoomController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->sendService = $this->createMock(SendService::class);
		$this->room = $this->createMock(Room::class);
		$this->participant = $this->createMock(Participant::class);

		$arguments = [];
		foreach ((new \ReflectionClass(RoomController::class))->getConstructor()->getParameters() as $parameter) {
			$type = (string)$parameter->getType();
			$arguments[] = match ($parameter->getName()) {
				'appName' => 'spreed',
				'userId' => 'alice',
				default => match ($type) {
					ParticipantService::class => $this->participantService,
					SendService::class => $this->sendService,
					default => $this->createMock($type),
				},
			};
		}
		$this->controller = new RoomController(...$arguments);
		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($this->participant);
	}

	public function testNotificationLevelOfMatrixConversation(): void {
		$this->room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_MATRIX);
		$this->participantService->expects(self::once())->method('updateNotificationLevel')->with($this->participant, Participant::NOTIFY_NEVER);
		$this->sendService->expects(self::once())
			->method('setNotificationLevel')
			->with($this->room, $this->participant, Participant::NOTIFY_NEVER)
			->willThrowException(new SendException('matrix', Http::STATUS_BAD_GATEWAY));

		self::assertSame(Http::STATUS_OK, $this->controller->setNotificationLevel(Participant::NOTIFY_NEVER)->getStatus());
	}

	public function testInvalidNotificationLevelIsNotSentToMatrix(): void {
		$this->room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_MATRIX);
		$this->participantService->method('updateNotificationLevel')->willThrowException(new \InvalidArgumentException('level'));
		$this->sendService->expects(self::never())->method('setNotificationLevel');

		self::assertSame(Http::STATUS_BAD_REQUEST, $this->controller->setNotificationLevel(Participant::NOTIFY_DEFAULT)->getStatus());
	}
}

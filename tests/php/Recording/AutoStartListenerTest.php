<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Recording;

use OCA\Talk\Config;
use OCA\Talk\Events\CallStartedEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Recording\AutoStartListener;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RecordingService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AutoStartListenerTest extends TestCase {
	protected RecordingService&MockObject $recordingService;
	protected ParticipantService&MockObject $participantService;
	protected Config&MockObject $config;
	protected LoggerInterface&MockObject $logger;
	protected AutoStartListener $listener;

	public function setUp(): void {
		parent::setUp();

		$this->recordingService = $this->createMock(RecordingService::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->config = $this->createMock(Config::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->listener = new AutoStartListener(
			$this->recordingService,
			$this->participantService,
			$this->config,
			$this->logger,
		);
	}

	private function room(bool $autoStart = true, bool $classified = false): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('isRecordingAutoStart')->willReturn($autoStart);
		$room->method('isClassified')->willReturn($classified);
		$room->method('isFederatedConversation')->willReturn(false);
		$room->method('getObjectType')->willReturn('');
		$room->method('getCallRecording')->willReturn(Room::RECORDING_NONE);
		$room->method('getToken')->willReturn('token123');
		return $room;
	}

	private function owner(string $id = 'room-owner'): Participant&MockObject {
		$attendee = $this->createMock(Attendee::class);
		$attendee->method('getActorId')->willReturn($id);
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);
		return $participant;
	}

	private function event(Room $room): CallStartedEvent {
		return new CallStartedEvent($room, new \DateTime(), Participant::FLAG_IN_CALL, [], null);
	}

	public function testStartsRecordingOnCallStartUsingRoomOwner(): void {
		$room = $this->room();
		$owner = $this->owner();
		$this->config->method('isRecordingEnabled')->willReturn(true);
		$this->participantService->method('getRoomOwner')->with($room)->willReturn($owner);
		$this->recordingService->expects($this->once())
			->method('start')
			->with($room, Room::RECORDING_VIDEO, 'room-owner', $owner);

		$this->listener->handle($this->event($room));
	}

	public function testDoesNotStartWhenAutoStartIsDisabled(): void {
		$room = $this->room(false);
		$this->config->method('isRecordingEnabled')->willReturn(true);
		$this->recordingService->expects($this->never())->method('start');

		$this->listener->handle($this->event($room));
	}

	public function testDoesNotStartWithoutAnOwner(): void {
		$room = $this->room();
		$this->config->method('isRecordingEnabled')->willReturn(true);
		$this->participantService->method('getRoomOwner')->with($room)->willReturn(null);
		$this->logger->expects($this->once())->method('warning');
		$this->recordingService->expects($this->never())->method('start');

		$this->listener->handle($this->event($room));
	}

	public function testDoesNotStartWhenRecordingIsDisabled(): void {
		$room = $this->room();
		$this->config->method('isRecordingEnabled')->willReturn(false);
		$this->recordingService->expects($this->never())->method('start');

		$this->listener->handle($this->event($room));
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Activity;

use OCA\Talk\Activity\Listener;
use OCA\Talk\Activity\Setting;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\CallEndedEvent;
use OCA\Talk\Room;
use OCA\Talk\RoomAttributes;
use OCA\Talk\Service\ParticipantService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ListenerTest extends TestCase {
	protected IManager&MockObject $activityManager;
	protected IUserSession&MockObject $userSession;
	protected ChatManager&MockObject $chatManager;
	protected ParticipantService&MockObject $participantService;
	protected LoggerInterface&MockObject $logger;
	protected ITimeFactory&MockObject $timeFactory;
	protected Setting&MockObject $setting;
	protected Listener $listener;

	public function setUp(): void {
		parent::setUp();

		$this->activityManager = $this->createMock(IManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->setting = $this->createMock(Setting::class);

		$this->listener = new Listener(
			$this->activityManager,
			$this->userSession,
			$this->chatManager,
			$this->participantService,
			$this->logger,
			$this->timeFactory,
			$this->setting,
		);
	}

	/**
	 * A real event, as the listener dispatches on `$event::class`, which would
	 * not match a mock.
	 */
	protected function callEndedEvent(Room $room): CallEndedEvent {
		return new CallEndedEvent($room, null, new \DateTime('@1234567890'));
	}

	protected function prepareCall(Room&MockObject $room): void {
		$room->method('isFederatedConversation')->willReturn(false);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);

		$this->timeFactory->method('getTime')->willReturn(1234567890 + 60);
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime());
		$this->participantService->method('getParticipantUserIds')->willReturn(['user1', 'user2']);
		$this->participantService->method('getParticipantActorIdsByActorType')->willReturn([]);
		$this->participantService->method('getActorsCountByType')->willReturn(0);
	}

	public function testDoesNotPublishCallActivityForClassifiedRoom(): void {
		$room = $this->createMock(Room::class);
		$room->method('getAttributes')->willReturn(RoomAttributes::CLASSIFIED->value);
		$room->method('isClassified')->willReturn(true);
		$this->prepareCall($room);

		// The system message stays, as it is part of the chat and expires with it
		$this->chatManager->expects(self::once())->method('addSystemMessage');

		// The activity would list who attended the call and outlives the conversation
		$this->activityManager->expects(self::never())->method('generateEvent');
		$this->activityManager->expects(self::never())->method('bulkPublish');

		$this->listener->handle($this->callEndedEvent($room));
	}

	public function testPublishesCallActivityForRegularRoom(): void {
		$room = $this->createMock(Room::class);
		$room->method('getAttributes')->willReturn(RoomAttributes::NONE->value);
		$room->method('isClassified')->willReturn(false);
		$room->method('getId')->willReturn(23);
		$this->prepareCall($room);

		$this->chatManager->expects(self::once())->method('addSystemMessage');

		$activity = $this->createMock(IEvent::class);
		$activity->method($this->anything())->willReturnSelf();
		$this->activityManager->method('generateEvent')->willReturn($activity);
		$this->activityManager->expects(self::once())
			->method('bulkPublish')
			->with($activity, ['user1', 'user2'], $this->setting);

		$this->listener->handle($this->callEndedEvent($room));
	}
}

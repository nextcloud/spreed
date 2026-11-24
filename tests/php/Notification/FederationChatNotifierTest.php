<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Notification;

use OCA\Talk\Config;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\ProxyCacheMessage;
use OCA\Talk\Model\Session;
use OCA\Talk\Notification\FederationChatNotifier;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ThreadService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class FederationChatNotifierTest extends TestCase {
	protected IAppConfig&MockObject $appConfig;
	protected IManager&MockObject $notificationManager;
	protected UserConverter&MockObject $userConverter;
	protected ThreadService&MockObject $threadService;
	protected FederationChatNotifier $notifier;

	public function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->userConverter = $this->createMock(UserConverter::class);
		$this->threadService = $this->createMock(ThreadService::class);

		$this->notifier = new FederationChatNotifier(
			$this->appConfig,
			$this->notificationManager,
			$this->userConverter,
			$this->threadService,
		);
	}

	protected function createParticipant(int $notificationLevel, ?Session $session = null): Participant&MockObject {
		$attendee = new Attendee();
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId('user1');
		$attendee->setNotificationLevel($notificationLevel);

		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);
		$participant->method('getSession')->willReturn($session);
		return $participant;
	}

	protected function createRoom(int $type = Room::TYPE_GROUP): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn($type);
		$room->method('getToken')->willReturn('room');
		return $room;
	}

	protected function createReactedMessage(string $actorType = Attendee::ACTOR_USERS, string $actorId = 'user1'): ProxyCacheMessage {
		$message = new ProxyCacheMessage();
		$message->setId(123);
		$message->setActorType($actorType);
		$message->setActorId($actorId);
		$message->setMessageType('comment');
		$message->setCreationDatetime(new \DateTime('2025-01-01T00:00:00+00:00'));
		return $message;
	}

	protected function createReactionData(string $actorType = Attendee::ACTOR_USERS, string $actorId = 'reactor'): array {
		return [
			'remoteMessageId' => 42,
			'actorType' => $actorType,
			'actorId' => $actorId,
			'actorDisplayName' => 'Reactor',
			'reaction' => '👍',
			'creationDatetime' => '2025-01-02T00:00:00+00:00',
		];
	}

	public static function dataHandleReactionNotificationLevel(): array {
		return [
			'always' => [Room::TYPE_GROUP, Participant::NOTIFY_ALWAYS, Participant::NOTIFY_MENTION, true],
			'default in one-to-one' => [Room::TYPE_ONE_TO_ONE, Participant::NOTIFY_DEFAULT, Participant::NOTIFY_MENTION, true],
			'default in group with always default' => [Room::TYPE_GROUP, Participant::NOTIFY_DEFAULT, Participant::NOTIFY_ALWAYS, true],
			'default in group with mention default' => [Room::TYPE_GROUP, Participant::NOTIFY_DEFAULT, Participant::NOTIFY_MENTION, false],
			'mention only' => [Room::TYPE_GROUP, Participant::NOTIFY_MENTION, Participant::NOTIFY_MENTION, false],
			'never' => [Room::TYPE_ONE_TO_ONE, Participant::NOTIFY_NEVER, Participant::NOTIFY_ALWAYS, false],
		];
	}

	#[DataProvider('dataHandleReactionNotificationLevel')]
	public function testHandleReactionNotificationLevel(int $roomType, int $notificationLevel, int $defaultLevel, bool $expectsNotification): void {
		$room = $this->createRoom($roomType);
		$participant = $this->createParticipant($notificationLevel);
		$message = $this->createReactedMessage();
		$reactionData = $this->createReactionData();

		$this->appConfig->method('getAppValueInt')
			->with(Config::DEFAULT_GROUP_NOTIFICATION, Participant::NOTIFY_ALWAYS)
			->willReturn($defaultLevel);

		$this->userConverter->method('convertTypeAndId')
			->with($room, Attendee::ACTOR_USERS, 'reactor')
			->willReturn(['type' => Attendee::ACTOR_FEDERATED_USERS, 'id' => 'reactor@remote']);

		if ($expectsNotification) {
			$notification = $this->createMock(INotification::class);
			$notification->method('setApp')->willReturnSelf();
			$notification->method('setObject')->willReturnSelf();
			$notification->method('setMessage')->willReturnSelf();
			$notification->method('setDateTime')->willReturnSelf();
			$notification->expects($this->once())
				->method('setSubject')
				->with('reaction', $this->callback(static fn (array $subjectData): bool => $subjectData['reaction'] === '👍'
					&& $subjectData['userType'] === Attendee::ACTOR_FEDERATED_USERS
					&& $subjectData['userId'] === 'reactor@remote'))
				->willReturnSelf();
			$notification->expects($this->once())
				->method('setUser')
				->with('user1')
				->willReturnSelf();
			$this->notificationManager->expects($this->once())
				->method('createNotification')
				->willReturn($notification);
			$this->notificationManager->expects($this->once())
				->method('notify')
				->with($notification);
		} else {
			$this->notificationManager->expects($this->never())
				->method('notify');
		}

		$this->notifier->handleReaction($room, $participant, $message, $reactionData);
	}

	public static function dataHandleReactionSkipped(): array {
		return [
			'not the message author' => [Attendee::ACTOR_USERS, 'user2', Attendee::ACTOR_USERS, 'reactor', ['type' => Attendee::ACTOR_FEDERATED_USERS, 'id' => 'reactor@remote']],
			'reaction to own message' => [Attendee::ACTOR_USERS, 'user1', Attendee::ACTOR_FEDERATED_USERS, 'user1@local', ['type' => Attendee::ACTOR_USERS, 'id' => 'user1']],
		];
	}

	#[DataProvider('dataHandleReactionSkipped')]
	public function testHandleReactionSkipped(string $authorType, string $authorId, string $reactorType, string $reactorId, array $convertedActor): void {
		$room = $this->createRoom();
		$participant = $this->createParticipant(Participant::NOTIFY_ALWAYS);
		$message = $this->createReactedMessage($authorType, $authorId);
		$reactionData = $this->createReactionData($reactorType, $reactorId);

		$this->userConverter->method('convertTypeAndId')
			->willReturn($convertedActor);

		$this->notificationManager->expects($this->never())
			->method('notify');

		$this->notifier->handleReaction($room, $participant, $message, $reactionData);
	}

	public function testHandleReactionWithActiveSessionStillNotifies(): void {
		// Same as \OCA\Talk\Chat\Notifier::notifyReacted() for local users:
		// an active session does not skip the notification
		$session = new Session();
		$session->setState(Session::STATE_ACTIVE);

		$room = $this->createRoom();
		$participant = $this->createParticipant(Participant::NOTIFY_ALWAYS, $session);
		$message = $this->createReactedMessage();
		$reactionData = $this->createReactionData();

		$this->userConverter->method('convertTypeAndId')
			->willReturn(['type' => Attendee::ACTOR_FEDERATED_USERS, 'id' => 'reactor@remote']);

		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$notification->method('setMessage')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$this->notificationManager->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notifier->handleReaction($room, $participant, $message, $reactionData);
	}

}

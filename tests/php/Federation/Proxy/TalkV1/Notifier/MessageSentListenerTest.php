<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Federation\Proxy\TalkV1\Notifier;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\ReactionAddedEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Federation\Proxy\TalkV1\Notifier\MessageSentListener;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\IL10N;
use OCP\L10N\IFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class MessageSentListenerTest extends TestCase {
	protected BackendNotifier&MockObject $backendNotifier;
	protected ParticipantService&MockObject $participantService;
	protected ICloudIdManager&MockObject $cloudIdManager;
	protected MessageParser&MockObject $messageParser;
	protected IFactory&MockObject $l10nFactory;
	protected ChatManager&MockObject $chatManager;
	protected ITimeFactory&MockObject $timeFactory;
	protected MessageSentListener $listener;

	public function setUp(): void {
		parent::setUp();

		$this->backendNotifier = $this->createMock(BackendNotifier::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);

		$this->listener = new MessageSentListener(
			$this->backendNotifier,
			$this->participantService,
			$this->cloudIdManager,
			$this->messageParser,
			$this->l10nFactory,
			$this->chatManager,
			$this->timeFactory,
		);
	}

	protected function createFederatedParticipant(int $attendeeId, int $permissions): Participant&MockObject {
		$attendee = new Attendee();
		$attendee->setId($attendeeId);
		$attendee->setActorType(Attendee::ACTOR_FEDERATED_USERS);
		$attendee->setActorId('user' . $attendeeId . '@remote');
		$attendee->setAccessToken('token' . $attendeeId);

		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);
		$participant->method('getPermissions')->willReturn($permissions);
		return $participant;
	}

	public static function dataLobby(): array {
		return [
			'no lobby' => [Webinary::LOBBY_NONE, [1, 2]],
			'lobby' => [Webinary::LOBBY_NON_MODERATORS, [2]],
		];
	}

	#[DataProvider('dataLobby')]
	public function testLobby(int $lobbyState, array $expectedAttendeeIds): void {
		$room = $this->createMock(Room::class);
		$room->method('hasFederatedParticipants')->willReturn(Room::HAS_FEDERATION_TALKv1);
		$room->method('getLobbyState')->willReturn($lobbyState);
		$room->method('getToken')->willReturn('room');

		$comment = $this->createMock(IComment::class);
		$comment->method('getId')->willReturn('42');
		$comment->method('getCreationDateTime')->willReturn(new \DateTime());

		$message = $this->createConfiguredMock(Message::class, [
			'getVisibility' => true,
			'getMessageType' => ChatManager::VERB_MESSAGE,
			'getMessageParameters' => [],
		]);

		$this->l10nFactory->method('get')->willReturn($this->createStub(IL10N::class));
		$this->messageParser->method('createMessage')->willReturn($message);

		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getRemote')->willReturn('remote');
		$this->cloudIdManager->method('resolveCloudId')->willReturn($cloudId);

		$this->participantService->method('getParticipantsByActorType')
			->with($room, Attendee::ACTOR_FEDERATED_USERS)
			->willReturn([
				$this->createFederatedParticipant(1, Attendee::PERMISSIONS_DEFAULT),
				$this->createFederatedParticipant(2, Attendee::PERMISSIONS_LOBBY_IGNORE),
			]);

		$notifiedAttendeeIds = [];
		$this->backendNotifier->method('sendMessageUpdate')
			->willReturnCallback(function (string $remote, int $attendeeId) use (&$notifiedAttendeeIds): bool {
				$notifiedAttendeeIds[] = $attendeeId;
				return true;
			});

		$this->listener->handle(new ChatMessageSentEvent($room, $comment));

		$this->assertSame($expectedAttendeeIds, $notifiedAttendeeIds);
	}

	public function testReactionAddedNotifiesFederatedAuthor(): void {
		$room = $this->createMock(Room::class);
		$room->method('hasFederatedParticipants')->willReturn(Room::HAS_FEDERATION_TALKv1);
		$room->method('getToken')->willReturn('room');

		$comment = $this->createMock(IComment::class);
		$comment->method('getId')->willReturn('42');
		$comment->method('getActorType')->willReturn(Attendee::ACTOR_FEDERATED_USERS);
		$comment->method('getActorId')->willReturn('author@remote');

		$reactionComment = $this->createMock(IComment::class);
		$reactionComment->method('getCreationDateTime')->willReturn(new \DateTime('2025-01-01T00:00:00+00:00'));

		$participant = $this->createFederatedParticipant(1, Attendee::PERMISSIONS_DEFAULT);
		$participant->getAttendee()->setActorId('author@remote');
		$this->participantService->method('getParticipantByActor')
			->with($room, Attendee::ACTOR_FEDERATED_USERS, 'author@remote')
			->willReturn($participant);

		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getRemote')->willReturn('remote');
		$this->cloudIdManager->method('resolveCloudId')
			->with('author@remote')
			->willReturn($cloudId);

		$room->method('getLobbyState')->willReturn(Webinary::LOBBY_NONE);

		$this->backendNotifier->expects($this->once())
			->method('sendReactionAdded')
			->with(
				'remote',
				1,
				'token1',
				'room',
				$this->callback(static fn (array $reactionData): bool => $reactionData['remoteMessageId'] === 42
					&& $reactionData['actorType'] === Attendee::ACTOR_USERS
					&& $reactionData['actorId'] === 'reactor'
					&& $reactionData['actorDisplayName'] === 'Reactor'
					&& $reactionData['reaction'] === '👍'
					&& $reactionData['creationDatetime'] === '2025-01-01T00:00:00+00:00'),
			)
			->willReturn(true);

		$this->listener->handle(new ReactionAddedEvent(
			$room,
			$comment,
			Attendee::ACTOR_USERS,
			'reactor',
			'Reactor',
			'👍',
			$reactionComment,
		));
	}

	public function testReactionAddedInLobbyIsSkipped(): void {
		$room = $this->createMock(Room::class);
		$room->method('hasFederatedParticipants')->willReturn(Room::HAS_FEDERATION_TALKv1);
		$room->method('getLobbyState')->willReturn(Webinary::LOBBY_NON_MODERATORS);

		$comment = $this->createMock(IComment::class);
		$comment->method('getActorType')->willReturn(Attendee::ACTOR_FEDERATED_USERS);
		$comment->method('getActorId')->willReturn('author@remote');

		$this->participantService->method('getParticipantByActor')
			->willReturn($this->createFederatedParticipant(1, Attendee::PERMISSIONS_DEFAULT));

		$this->backendNotifier->expects($this->never())
			->method('sendReactionAdded');

		$this->listener->handle(new ReactionAddedEvent(
			$room,
			$comment,
			Attendee::ACTOR_USERS,
			'reactor',
			'Reactor',
			'👍',
		));
	}

	public function testReactionAddedDoesNotRemoveParticipantWhenRemoteDoesNotKnowTheNotification(): void {
		$room = $this->createMock(Room::class);
		$room->method('hasFederatedParticipants')->willReturn(Room::HAS_FEDERATION_TALKv1);
		$room->method('getLobbyState')->willReturn(Webinary::LOBBY_NONE);
		$room->method('getToken')->willReturn('room');

		$comment = $this->createMock(IComment::class);
		$comment->method('getId')->willReturn('42');
		$comment->method('getActorType')->willReturn(Attendee::ACTOR_FEDERATED_USERS);
		$comment->method('getActorId')->willReturn('author@remote');

		$participant = $this->createFederatedParticipant(1, Attendee::PERMISSIONS_DEFAULT);
		$participant->getAttendee()->setActorId('author@remote');
		$this->participantService->method('getParticipantByActor')
			->willReturn($participant);

		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getRemote')->willReturn('remote');
		$this->cloudIdManager->method('resolveCloudId')
			->willReturn($cloudId);

		// The remote server runs an older Talk version and answers RESOURCE_NOT_FOUND
		$this->backendNotifier->method('sendReactionAdded')
			->willReturn(null);

		$this->participantService->expects($this->never())
			->method('removeAttendee');

		$this->listener->handle(new ReactionAddedEvent(
			$room,
			$comment,
			Attendee::ACTOR_USERS,
			'reactor',
			'Reactor',
			'👍',
		));
	}

	public static function dataReactionAddedSkipped(): array {
		return [
			'local author' => [Attendee::ACTOR_USERS, 'author', Attendee::ACTOR_USERS, 'reactor'],
			'reaction to own message' => [Attendee::ACTOR_FEDERATED_USERS, 'author@remote', Attendee::ACTOR_FEDERATED_USERS, 'author@remote'],
			'author left the room' => [Attendee::ACTOR_FEDERATED_USERS, 'author@remote', Attendee::ACTOR_USERS, 'reactor'],
		];
	}

	#[DataProvider('dataReactionAddedSkipped')]
	public function testReactionAddedSkipped(string $authorType, string $authorId, string $reactorType, string $reactorId): void {
		$room = $this->createMock(Room::class);
		$room->method('hasFederatedParticipants')->willReturn(Room::HAS_FEDERATION_TALKv1);

		$comment = $this->createMock(IComment::class);
		$comment->method('getActorType')->willReturn($authorType);
		$comment->method('getActorId')->willReturn($authorId);

		if ($authorType === Attendee::ACTOR_FEDERATED_USERS && $reactorId !== $authorId) {
			$this->participantService->method('getParticipantByActor')
				->willThrowException(new ParticipantNotFoundException());
		}

		$this->backendNotifier->expects($this->never())
			->method('sendReactionAdded');

		$this->listener->handle(new ReactionAddedEvent(
			$room,
			$comment,
			$reactorType,
			$reactorId,
			'Reactor',
			'👍',
		));
	}
}

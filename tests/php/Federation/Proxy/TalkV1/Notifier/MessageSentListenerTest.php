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
use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Federation\Proxy\TalkV1\Notifier\MessageSentListener;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Webinary;
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
	protected MessageSentListener $listener;

	public function setUp(): void {
		parent::setUp();

		$this->backendNotifier = $this->createMock(BackendNotifier::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->chatManager = $this->createMock(ChatManager::class);

		$this->listener = new MessageSentListener(
			$this->backendNotifier,
			$this->participantService,
			$this->cloudIdManager,
			$this->messageParser,
			$this->l10nFactory,
			$this->chatManager,
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
}

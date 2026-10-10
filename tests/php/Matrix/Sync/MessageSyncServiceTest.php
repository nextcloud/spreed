<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Sync;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Matrix\Client\Model\Event;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\EventMap;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Model\MatrixMember;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Sync\MessageSyncService;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class MessageSyncServiceTest extends TestCase {
	private ChatManager&MockObject $chatManager;
	private ReactionManager&MockObject $reactionManager;
	private ICommentsManager&MockObject $commentsManager;
	private ParticipantService&MockObject $participantService;
	private Room&MockObject $room;
	private MatrixRoom $matrixRoom;
	/** @var array<string, EventMap> */
	private array $eventMaps = [];
	/** @var array<string, IComment> */
	private array $comments = [];
	private MessageSyncService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->reactionManager = $this->createMock(ReactionManager::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->commentsManager->method('get')->willReturnCallback(fn (string $id): IComment => $this->comments[$id] ?? throw new NotFoundException());
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->participantService->method('getParticipantByActor')->willReturnCallback(fn (Room $room, string $actorType, string $actorId): Participant => match ($actorType . '/' . $actorId) {
			'matrix/@bob:example.org' => $this->participant(Attendee::ACTOR_MATRIX, '@bob:example.org', 'Bob'),
			'users/alice' => $this->participant(Attendee::ACTOR_USERS, 'alice', 'Alice'),
			default => throw new ParticipantNotFoundException(),
		});

		$eventMapMapper = $this->createMock(EventMapMapper::class);
		$eventMapMapper->method('insertIfNew')->willReturnCallback(function (EventMap $eventMap): bool {
			if (isset($this->eventMaps[$eventMap->getEventId()])) {
				return false;
			}
			self::invokePrivate($eventMap, 'id', ['55']);
			$this->eventMaps[$eventMap->getEventId()] = $eventMap;
			return true;
		});
		$eventMapMapper->method('findByEventId')->willReturnCallback(function (string $matrixRoomId, string $eventId): ?EventMap {
			$eventMap = $this->eventMaps[$eventId] ?? null;
			return $eventMap?->getMatrixRoomId() === $matrixRoomId ? $eventMap : null;
		});

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')->willReturnCallback(static fn (string $time): \DateTime => new \DateTime($time));
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRouteAbsolute')->willReturnCallback(static fn (string $route, array $parameters): string => 'https://cloud.example/' . $route . '/' . $parameters['id']);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		$this->room = $this->createMock(Room::class);
		$this->matrixRoom = MatrixRoom::fromRow(['id' => '100', 'room_id' => 23, 'matrix_room_id' => '!room:example.org']);

		$this->service = new MessageSyncService(
			$this->chatManager,
			$this->reactionManager,
			$this->commentsManager,
			$this->participantService,
			$eventMapMapper,
			$timeFactory,
			$urlGenerator,
			$l,
			$this->createMock(LoggerInterface::class),
		);
	}

	private function participant(string $actorType, string $actorId, string $displayName): Participant&MockObject {
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn(Attendee::fromRow([
			'actor_type' => $actorType,
			'actor_id' => $actorId,
			'display_name' => $displayName,
		]));
		return $participant;
	}

	private function comment(int $id, string $verb = ChatManager::VERB_MESSAGE, string $actorType = Attendee::ACTOR_MATRIX, string $actorId = '@bob:example.org', string $message = '', int $parentId = 0): IComment&MockObject {
		$comment = $this->createMock(IComment::class);
		$comment->method('getId')->willReturn((string)$id);
		$comment->method('getVerb')->willReturn($verb);
		$comment->method('getActorType')->willReturn($actorType);
		$comment->method('getActorId')->willReturn($actorId);
		$comment->method('getMessage')->willReturn($message);
		$comment->method('getParentId')->willReturn((string)$parentId);
		$this->comments[(string)$id] = $comment;
		return $comment;
	}

	private function mapped(string $eventId, int $commentId, string $sender = '@bob:example.org', string $type = 'm.room.message', string $matrixRoomId = '100'): void {
		$eventMap = EventMap::fromRow([
			'id' => '1' . $commentId,
			'matrix_room_id' => $matrixRoomId,
			'event_id' => $eventId,
			'event_type' => $type,
			'sender' => $sender,
			'comment_id' => $commentId,
		]);
		$this->eventMaps[$eventId] = $eventMap;
	}

	private static function event(string $eventId, string $type, array $content, string $sender = '@bob:example.org', ?string $redacts = null): Event {
		return new Event($eventId, $type, $sender, $content, null, 1791460800000, $redacts);
	}

	/**
	 * @param list<Event> $timeline
	 */
	private function apply(array $timeline, bool $silent = false): int {
		$members = [
			'@bob:example.org' => MatrixMember::fromRow(['mxid' => '@bob:example.org', 'membership' => 'join', 'display_name' => 'Bob']),
		];
		$accounts = [
			'@alice:example.org' => Account::fromRow(['id' => '7', 'user_id' => 'alice', 'mxid' => '@alice:example.org']),
		];
		return $this->service->apply($this->room, $this->matrixRoom, $timeline, $members, $accounts, $silent);
	}

	public function testMessage(): void {
		$this->chatManager->expects(self::once())
			->method('sendMessage')
			->willReturnCallback(function (Room $room, ?Participant $participant, string $actorType, string $actorId, string $message, \DateTime $creationDateTime, ?IComment $replyTo, string $referenceId, bool $silent): IComment {
				self::assertSame(Attendee::ACTOR_MATRIX, $actorType);
				self::assertSame('@bob:example.org', $actorId);
				self::assertSame('Hi @"alice", **welcome** @all', $message);
				self::assertSame(1791460800, $creationDateTime->getTimestamp());
				self::assertNull($replyTo);
				self::assertTrue($silent);
				return $this->comment(11);
			});

		self::assertSame(1, $this->apply([self::event('$message', 'm.room.message', [
			'msgtype' => 'm.text',
			'body' => 'Hi Alice, welcome @room',
			'format' => 'org.matrix.custom.html',
			'formatted_body' => 'Hi <a href="https://matrix.to/#/@alice:example.org">Alice</a>, <strong>welcome</strong> @room',
			'm.mentions' => ['room' => true],
		])], true));
		self::assertSame(11, $this->eventMaps['$message']->getCommentId());
	}

	public function testMessageFromLinkedUserReplyingWithFallback(): void {
		$parent = $this->comment(11);
		$this->mapped('$parent', 11);
		$this->chatManager->expects(self::once())
			->method('sendMessage')
			->willReturnCallback(function (Room $room, ?Participant $participant, string $actorType, string $actorId, string $message, \DateTime $creationDateTime, ?IComment $replyTo) use ($parent): IComment {
				self::assertSame(Attendee::ACTOR_USERS, $actorType);
				self::assertSame('alice', $actorId);
				self::assertSame('Thanks', $message);
				self::assertSame($parent, $replyTo);
				return $this->comment(12);
			});

		self::assertSame(1, $this->apply([self::event('$reply', 'm.room.message', [
			'msgtype' => 'm.text',
			'body' => "> <@bob:example.org> Hello\n\nThanks",
			'm.relates_to' => ['m.in_reply_to' => ['event_id' => '$parent']],
		], '@alice:example.org')]));
	}

	public function testEmoteAndEncryptedMessage(): void {
		$messages = [];
		$this->chatManager->expects(self::exactly(2))
			->method('sendMessage')
			->willReturnCallback(function (Room $room, ?Participant $participant, string $actorType, string $actorId, string $message) use (&$messages): IComment {
				$messages[] = $message;
				return $this->comment(10 + count($messages));
			});

		self::assertSame(2, $this->apply([
			self::event('$emote', 'm.room.message', ['msgtype' => 'm.emote', 'body' => 'waves']),
			self::event('$encrypted', 'm.room.encrypted', ['algorithm' => 'm.megolm.v1.aes-sha2', 'ciphertext' => 'abc']),
		]));
		self::assertSame(['* Bob waves', 'Encrypted message, end-to-end encrypted Matrix rooms are not supported yet'], $messages);
	}

	public function testAlreadyMirroredAndIgnoredEvents(): void {
		$this->mapped('$known', 11);
		$this->chatManager->expects(self::never())->method('sendMessage');

		self::assertSame(0, $this->apply([
			self::event('$known', 'm.room.message', ['msgtype' => 'm.text', 'body' => 'Again']),
			self::event('$call', 'm.call.invite', []),
			self::event('$redacted', 'm.room.message', []),
		]));
		self::assertArrayNotHasKey('$call', $this->eventMaps);
	}

	public function testEdit(): void {
		$original = $this->comment(11);
		$this->mapped('$original', 11);
		$this->chatManager->expects(self::once())
			->method('editMessage')
			->with($this->room, $original, self::anything(), self::anything(), 'Fixed **typo**')
			->willReturn($this->comment(12, ChatManager::VERB_SYSTEM));

		self::assertSame(0, $this->apply([self::event('$edit', 'm.room.message', [
			'msgtype' => 'm.text',
			'body' => '* Fixed typo',
			'm.new_content' => ['msgtype' => 'm.text', 'body' => 'Fixed typo', 'format' => 'org.matrix.custom.html', 'formatted_body' => 'Fixed <b>typo</b>'],
			'm.relates_to' => ['rel_type' => 'm.replace', 'event_id' => '$original'],
		])]));
	}

	public function testEditOfOtherSenderIsIgnored(): void {
		$this->comment(11);
		$this->mapped('$original', 11, '@alice:example.org');
		$this->chatManager->expects(self::never())->method('editMessage');
		$this->chatManager->expects(self::never())->method('sendMessage');

		$this->apply([self::event('$edit', 'm.room.message', [
			'msgtype' => 'm.text',
			'body' => '* Hijacked',
			'm.new_content' => ['msgtype' => 'm.text', 'body' => 'Hijacked'],
			'm.relates_to' => ['rel_type' => 'm.replace', 'event_id' => '$original'],
		])]);
	}

	public function testReaction(): void {
		$this->comment(11);
		$this->mapped('$original', 11);
		$this->reactionManager->expects(self::once())
			->method('addReactionMessage')
			->with($this->room, Attendee::ACTOR_MATRIX, '@bob:example.org', 'Bob', 11, '👍')
			->willReturn($this->comment(12, ChatManager::VERB_REACTION));

		self::assertSame(0, $this->apply([self::event('$reaction', 'm.reaction', [
			'm.relates_to' => ['rel_type' => 'm.annotation', 'event_id' => '$original', 'key' => '👍'],
		])]));
		self::assertSame(12, $this->eventMaps['$reaction']->getCommentId());
	}

	public function testRedactionOfMessage(): void {
		$original = $this->comment(11);
		$this->mapped('$original', 11);
		$this->chatManager->expects(self::once())
			->method('deleteMessage')
			->with($this->room, $original, self::anything(), self::anything())
			->willReturn($this->comment(12, ChatManager::VERB_SYSTEM));

		$this->apply([self::event('$redaction', 'm.room.redaction', [], '@bob:example.org', '$original')]);
	}

	public function testRedactionOfReaction(): void {
		$this->comment(12, ChatManager::VERB_REACTION, Attendee::ACTOR_MATRIX, '@bob:example.org', '👍', 11);
		$this->mapped('$reaction', 12, '@bob:example.org', 'm.reaction');
		$this->reactionManager->expects(self::once())
			->method('deleteReactionMessage')
			->with($this->room, Attendee::ACTOR_MATRIX, '@bob:example.org', 'Bob', 11, '👍');
		$this->chatManager->expects(self::never())->method('deleteMessage');

		$this->apply([self::event('$redaction', 'm.room.redaction', [], '@bob:example.org', '$reaction')]);
	}

	public function testRedactionByOtherSenderIsIgnored(): void {
		$this->comment(11, ChatManager::VERB_MESSAGE, Attendee::ACTOR_USERS, 'alice');
		$this->mapped('$original', 11, '@alice:example.org');
		$this->chatManager->expects(self::never())->method('deleteMessage');

		$this->apply([self::event('$redaction', 'm.room.redaction', [], '@bob:example.org', '$original')]);
	}

	public function testRedactionByModerator(): void {
		$this->matrixRoom = MatrixRoom::fromRow(['id' => '100', 'room_id' => 23, 'matrix_room_id' => '!room:example.org', 'power_levels' => json_encode(['users' => ['@bob:example.org' => 50]])]);
		$original = $this->comment(11, ChatManager::VERB_MESSAGE, Attendee::ACTOR_USERS, 'alice');
		$this->mapped('$original', 11, '@alice:example.org');
		$this->chatManager->expects(self::once())
			->method('deleteMessage')
			->with($this->room, $original, self::anything(), self::anything())
			->willReturn($this->comment(12, ChatManager::VERB_SYSTEM));

		$this->apply([self::event('$redaction', 'm.room.redaction', [], '@bob:example.org', '$original')]);
	}

	public function testEventsOfOtherRoomsAreNotReferenced(): void {
		$this->comment(11);
		$this->mapped('$other', 11, '@bob:example.org', 'm.room.message', '200');
		$this->chatManager->expects(self::never())->method('deleteMessage');
		$this->chatManager->expects(self::never())->method('editMessage');
		$this->chatManager->expects(self::once())
			->method('sendMessage')
			->willReturnCallback(function (Room $room, ?Participant $participant, string $actorType, string $actorId, string $message, \DateTime $creationDateTime, ?IComment $replyTo): IComment {
				self::assertNull($replyTo);
				return $this->comment(12);
			});

		$this->apply([
			self::event('$reply', 'm.room.message', ['msgtype' => 'm.text', 'body' => 'Leak', 'm.relates_to' => ['m.in_reply_to' => ['event_id' => '$other']]]),
			self::event('$edit', 'm.room.message', [
				'msgtype' => 'm.text',
				'body' => '* Changed',
				'm.new_content' => ['msgtype' => 'm.text', 'body' => 'Changed'],
				'm.relates_to' => ['rel_type' => 'm.replace', 'event_id' => '$other'],
			]),
			self::event('$redaction', 'm.room.redaction', [], '@bob:example.org', '$other'),
		]);
	}

	public function testAttachment(): void {
		$this->chatManager->expects(self::never())->method('sendMessage');
		$this->chatManager->expects(self::once())
			->method('addSystemMessage')
			->willReturnCallback(function (Room $room, ?Participant $participant, string $actorType, string $actorId, string $message, \DateTime $creationDateTime, bool $sendNotifications): IComment {
				self::assertSame(Attendee::ACTOR_MATRIX, $actorType);
				self::assertFalse($sendNotifications);
				$object = [
					'type' => 'highlight',
					'id' => 'matrix-media/55',
					'name' => 'cat.jpg',
					'link' => 'https://cloud.example/spreed.MatrixMedia.download/55',
					'mxc' => 'mxc://example.org/abc',
				];
				self::assertSame(['message' => 'object_shared', 'parameters' => ['objectType' => 'highlight', 'objectId' => 'matrix-media/55', 'metaData' => $object]], json_decode($message, true));
				return $this->comment(11, ChatManager::VERB_OBJECT_SHARED);
			});

		self::assertSame(1, $this->apply([self::event('$image', 'm.room.message', ['msgtype' => 'm.image', 'body' => 'cat.jpg', 'url' => 'mxc://example.org/abc'])], true));
	}

	public function testLocation(): void {
		$this->chatManager->expects(self::once())
			->method('addSystemMessage')
			->willReturnCallback(function (Room $room, ?Participant $participant, string $actorType, string $actorId, string $message): IComment {
				self::assertSame([
					'type' => 'geo-location',
					'id' => 'geo:52.52,13.405',
					'name' => 'Berlin',
					'latitude' => '52.52',
					'longitude' => '13.405',
				], json_decode($message, true)['parameters']['metaData']);
				return $this->comment(11, ChatManager::VERB_OBJECT_SHARED);
			});

		$this->apply([self::event('$location', 'm.room.message', ['msgtype' => 'm.location', 'body' => 'Berlin', 'geo_uri' => 'geo:52.52,13.405'])]);
	}

	public function testAttachmentWithoutContentUriIsText(): void {
		$this->chatManager->expects(self::never())->method('addSystemMessage');
		$this->chatManager->expects(self::once())
			->method('sendMessage')
			->willReturnCallback(function (Room $room, ?Participant $participant, string $actorType, string $actorId, string $message): IComment {
				self::assertSame('secret.pdf', $message);
				return $this->comment(11);
			});

		$this->apply([self::event('$file', 'm.room.message', ['msgtype' => 'm.file', 'body' => 'secret.pdf', 'file' => ['url' => 'mxc://example.org/encrypted']])]);
	}
}

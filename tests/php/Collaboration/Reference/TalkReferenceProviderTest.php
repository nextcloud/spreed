<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Collaboration\Resources;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Collaboration\Reference\TalkReferenceProvider;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\ProxyCacheMessageMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Webinary;
use OCP\Comments\IComment;
use OCP\Comments\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class TalkReferenceProviderTest extends TestCase {
	protected IURLGenerator&MockObject $urlGenerator;
	protected Manager&MockObject $roomManager;
	protected ParticipantService&MockObject $participantService;
	protected ChatManager&MockObject $chatManager;
	protected ProxyCacheMessageMapper&MockObject $proxyCacheMessageMapper;
	protected AvatarService&MockObject $avatarService;
	protected MessageParser&MockObject $messageParser;
	protected IL10N&MockObject $l;
	protected ?TalkReferenceProvider $provider = null;

	public function setUp(): void {
		parent::setUp();

		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->roomManager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->proxyCacheMessageMapper = $this->createMock(ProxyCacheMessageMapper::class);
		$this->avatarService = $this->createMock(AvatarService::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->l = $this->createMock(IL10N::class);

		$this->provider = new TalkReferenceProvider(
			$this->urlGenerator,
			$this->roomManager,
			$this->participantService,
			$this->chatManager,
			$this->proxyCacheMessageMapper,
			$this->avatarService,
			$this->messageParser,
			$this->l,
			'test'
		);
	}

	public static function dataGetTalkAppLinkToken(): array {
		return [
			['https://localhost/', null],
			['https://localhost/call', null],
			['https://localhost/call/abcdef', ['token' => 'abcdef', 'message' => null]],
			['https://localhost/call/abcdef?query=1', ['token' => 'abcdef', 'message' => null]],
			['https://localhost/call/abcdef#hash=1', ['token' => 'abcdef', 'message' => null]],
			['https://localhost/call/abcdef#message_123', ['token' => 'abcdef', 'message' => 123]],
			['https://localhost/call/abcdef?query=1#message_123', ['token' => 'abcdef', 'message' => 123]],
			['https://localhost/call/abcdef?query=1#message_123bcd', ['token' => 'abcdef', 'message' => null]],
		];
	}

	#[DataProvider('dataGetTalkAppLinkToken')]
	public function testGetTalkAppLinkToken(string $reference, ?array $expected): void {
		$this->urlGenerator->expects($this->any())
			->method('getAbsoluteURL')
			->willReturnCallback(static fn ($url) => 'https://localhost' . $url);

		$actual = self::invokePrivate($this->provider, 'getTalkAppLinkToken', [$reference]);
		self::assertSame($expected, $actual);
	}

	public static function dataResolveReferenceLobby(): array {
		return [
			'no lobby' => [Webinary::LOBBY_NONE, 0, true],
			'lobby' => [Webinary::LOBBY_NON_MODERATORS, 0, false],
			'lobby with bypass' => [Webinary::LOBBY_NON_MODERATORS, Attendee::PERMISSIONS_LOBBY_IGNORE, true],
		];
	}

	#[DataProvider('dataResolveReferenceLobby')]
	public function testResolveReferenceLobby(int $lobbyState, int $permissions, bool $canSeeMessage): void {
		$this->urlGenerator->method('getAbsoluteURL')
			->willReturnCallback(static fn ($url) => 'https://localhost' . $url);
		$this->l->method('t')
			->willReturnArgument(0);

		$room = $this->createMock(Room::class);
		$room->method('getLobbyState')->willReturn($lobbyState);
		$room->method('getDescription')->willReturn('description');
		$this->roomManager->method('getRoomForUserByToken')
			->with('abcdef', 'test')
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$participant->method('getPermissions')->willReturn($permissions);
		$this->participantService->method('getParticipant')
			->willReturn($participant);

		// Loading the comment is only attempted when the message may be seen
		$this->chatManager->expects($canSeeMessage ? $this->once() : $this->never())
			->method('getComment')
			->with($room, '123')
			->willThrowException(new NotFoundException());

		$reference = $this->provider->resolveReference('https://localhost/call/abcdef#message_123');
		if ($canSeeMessage) {
			self::assertFalse($reference->getAccessible());
		} else {
			self::assertTrue($reference->getAccessible());
			self::assertSame('Message in private conversation', $reference->getTitle());
			self::assertSame('', $reference->getDescription());
			self::assertNull($reference->getImageUrl());
			self::assertArrayNotHasKey('message-id', $reference->getRichObject());
		}
	}

	public static function dataResolveReferenceMessageRichObject(): array {
		$base = [
			'id' => 'abcdef',
			'name' => 'Conversation',
			'link' => 'https://localhost/call/abcdef',
			'call-type' => 'group',
			'description' => 'Hello',
			'avatar-version' => 'v1',
			'message-id' => '123',
			'actor-type' => Attendee::ACTOR_USERS,
			'actor-id' => 'author',
			'actor-display-name' => 'Author',
			'timestamp' => '1700000000',
		];
		return [
			'group' => [Room::TYPE_GROUP, false, 'Message of Author in Conversation', $base],
			'one-to-one' => [Room::TYPE_ONE_TO_ONE, false, 'Message of Author', ['call-type' => 'one2one', 'user-id' => 'other'] + $base],
			'federated author' => [Room::TYPE_GROUP, false, 'Message of Author (remote.example:8443) in Conversation', ['actor-type' => Attendee::ACTOR_FEDERATED_USERS, 'actor-id' => 'author@sub@remote.example:8443', 'actor-display-name' => 'Author (remote.example:8443)'] + $base],
			'classified' => [Room::TYPE_GROUP, true, 'Message in private conversation', [
				'id' => 'abcdef',
				'name' => 'Message in private conversation',
				'link' => 'https://localhost/call/abcdef',
				'call-type' => 'group',
				'description' => '',
				'message-id' => '123',
			]],
		];
	}

	#[DataProvider('dataResolveReferenceMessageRichObject')]
	public function testResolveReferenceMessageRichObject(int $roomType, bool $classified, string $title, array $expected): void {
		$this->urlGenerator->method('getAbsoluteURL')
			->willReturnCallback(static fn ($url) => 'https://localhost' . $url);
		$room = $this->createMock(Room::class);
		$room->method('getToken')->willReturn('abcdef');
		$room->method('getType')->willReturn($roomType);
		$room->method('isClassified')->willReturn($classified);
		$room->method('getSecondParticipant')->with('test')->willReturn('other');
		$room->method('getDisplayName')->willReturn('Conversation');
		$room->method('getLobbyState')->willReturn(Webinary::LOBBY_NONE);
		$this->roomManager->method('getRoomForUserByToken')
			->with('abcdef', 'test')
			->willReturn($room);
		$this->participantService->method('getParticipant')
			->willReturn($this->createMock(Participant::class));
		$this->urlGenerator->method('linkToRouteAbsolute')
			->willReturn('https://localhost/call/abcdef');
		$this->avatarService->method('getAvatarVersion')
			->willReturn('v1');
		$this->l->method('t')
			->willReturnArgument(0);

		$comment = $this->createMock(IComment::class);
		$comment->method('getCreationDateTime')
			->willReturn(new \DateTime('@1700000000'));
		$this->chatManager->method('getComment')
			->with($room, '123')
			->willReturn($comment);

		$message = $this->createMock(Message::class);
		$message->method('getMessageParameters')->willReturn([]);
		$message->method('getMessage')->willReturn('Hello');
		$message->method('getActorType')->willReturn($expected['actor-type'] ?? Attendee::ACTOR_USERS);
		$message->method('getActorId')->willReturn($expected['actor-id'] ?? 'author');
		$message->method('getActorDisplayName')->willReturn('Author');
		$this->messageParser->method('createMessage')
			->willReturn($message);

		$reference = $this->provider->resolveReference('https://localhost/call/abcdef#message_123');
		self::assertSame($title, $reference->getTitle());
		self::assertSame($expected['description'], $reference->getDescription());
		self::assertEquals($expected, $reference->getRichObject());
	}

	public static function dataResolveReferenceConversation(): array {
		return [
			'regular' => [false, 'Conversation', 'description', 'https://localhost/avatar', 'v1'],
			'classified' => [true, 'Private conversation', '', null, null],
		];
	}

	#[DataProvider('dataResolveReferenceConversation')]
	public function testResolveReferenceConversation(bool $classified, string $name, string $description, ?string $imageUrl, ?string $avatarVersion): void {
		$this->urlGenerator->method('getAbsoluteURL')
			->willReturnCallback(static fn ($url) => 'https://localhost' . $url);

		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);
		$room->method('isClassified')->willReturn($classified);
		$room->method('getDisplayName')->willReturn('Conversation');
		$room->method('getDescription')->willReturn('description');
		$this->roomManager->method('getRoomForUserByToken')
			->with('abcdef', 'test')
			->willReturn($room);
		$this->participantService->method('getParticipant')
			->willReturn($this->createMock(Participant::class));
		$this->avatarService->method('getAvatarUrl')
			->willReturn('https://localhost/avatar');
		$this->avatarService->method('getAvatarVersion')
			->willReturn('v1');
		$this->l->method('t')
			->willReturnArgument(0);

		$reference = $this->provider->resolveReference('https://localhost/call/abcdef');
		self::assertSame($name, $reference->getTitle());
		self::assertSame($name, $reference->getRichObject()['name']);
		self::assertSame($description, $reference->getDescription());
		self::assertSame($description, $reference->getRichObject()['description']);
		self::assertSame($imageUrl, $reference->getImageUrl());
		self::assertSame($avatarVersion, $reference->getRichObject()['avatar-version'] ?? null);
	}

	public static function dataResolveReferenceNotJoined(): array {
		return [
			'listed' => [true],
			'not listed' => [false],
		];
	}

	#[DataProvider('dataResolveReferenceNotJoined')]
	public function testResolveReferenceNotJoined(bool $listable): void {
		$this->urlGenerator->method('getAbsoluteURL')
			->willReturnCallback(static fn ($url) => 'https://localhost' . $url);

		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::TYPE_PUBLIC);
		$room->method('getDescription')->willReturn('description');
		$this->roomManager->method('getRoomForUserByToken')
			->with('abcdef', 'test')
			->willReturn($room);
		$this->roomManager->method('isRoomListableByUser')
			->with($room, 'test')
			->willReturn($listable);
		$this->participantService->method('getParticipant')
			->willThrowException(new ParticipantNotFoundException());
		$this->avatarService->method('getAvatarUrl')
			->willReturn('https://localhost/avatar');
		$this->avatarService->method('getAvatarVersion')
			->willReturn('v1');

		$reference = $this->provider->resolveReference('https://localhost/call/abcdef');
		self::assertTrue($reference->getAccessible());
		self::assertSame($listable ? 'description' : '', $reference->getDescription());
		self::assertSame($listable ? 'https://localhost/avatar' : null, $reference->getImageUrl());
		self::assertSame($listable ? 'v1' : null, $reference->getRichObject()['avatar-version'] ?? null);
	}
}

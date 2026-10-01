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
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\ProxyCacheMessageMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Webinary;
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
			self::assertSame('description', $reference->getDescription());
			self::assertArrayNotHasKey('message-id', $reference->getRichObject());
		}
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Dashboard;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Config;
use OCA\Talk\Dashboard\TalkWidget;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ProxyCacheMessageService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class TalkWidgetTest extends TestCase {
	protected Manager&MockObject $manager;
	protected ParticipantService&MockObject $participantService;
	protected MessageParser&MockObject $messageParser;
	protected ChatManager&MockObject $chatManager;
	protected TalkWidget $widget;

	public function setUp(): void {
		parent::setUp();

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->chatManager = $this->createMock(ChatManager::class);

		$this->widget = new TalkWidget(
			$this->createMock(IUserSession::class),
			$this->createMock(Config::class),
			$this->createMock(IURLGenerator::class),
			$l10n,
			$this->manager,
			$this->createMock(AvatarService::class),
			$this->participantService,
			$this->messageParser,
			$this->chatManager,
			$this->createMock(ProxyCacheMessageService::class),
			$this->createMock(IEventDispatcher::class),
			$this->createMock(ITimeFactory::class),
		);
	}

	public static function dataHasUnreadMention(): array {
		return [
			'no mention, never read' => [ChatManager::UNREAD_FIRST_MESSAGE, 0, false],
			'no mention, migrated marker' => [ChatManager::UNREAD_MIGRATION, 0, false],
			'no mention, nothing read' => [0, 0, false],
			'no mention, read' => [10, 0, false],
			'mention, never read' => [ChatManager::UNREAD_FIRST_MESSAGE, 5, true],
			'mention after the read marker' => [4, 5, true],
			'mention at the read marker' => [5, 5, false],
			'mention before the read marker' => [6, 5, false],
		];
	}

	#[DataProvider('dataHasUnreadMention')]
	public function testHasUnreadMention(int $lastRead, int $lastMention, bool $expected): void {
		$attendee = new Attendee();
		$attendee->setLastReadMessage($lastRead);
		$attendee->setLastMentionMessage($lastMention);

		$this->assertSame($expected, self::invokePrivate($this->widget, 'hasUnreadMention', [$attendee]));
	}

	public function testGetItemsV2UnreadConversationWithoutMention(): void {
		$comment = $this->createMock(IComment::class);

		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn('');
		$room->method('getLobbyState')->willReturn(Webinary::LOBBY_NONE);
		$room->method('getCallFlag')->willReturn(Participant::FLAG_DISCONNECTED);
		$room->method('isFederatedConversation')->willReturn(false);
		$room->method('getType')->willReturn(Room::TYPE_CHANGELOG);
		$room->method('getLastMessageId')->willReturn(110);
		$room->method('getLastMessage')->willReturn($comment);
		$room->method('getDisplayName')->willReturn('user');
		$room->method('getToken')->willReturn('token');

		// Never read (-2) and never mentioned (0), as the changelog conversation
		// of a user who did not open it yet
		$attendee = new Attendee();
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId('user');
		$attendee->setLastReadMessage(ChatManager::UNREAD_FIRST_MESSAGE);
		$attendee->setLastMentionMessage(0);

		$this->manager->method('getRoomsForUser')->willReturn([$room]);
		$this->participantService->method('getParticipant')
			->willReturn(new Participant($room, $attendee, null));

		$message = $this->createMock(Message::class);
		$message->method('getVisibility')->willReturn(true);
		$message->method('getMessageParameters')->willReturn([]);
		$message->method('getMessage')->willReturn('Changelog');
		$message->method('getExpirationDateTime')->willReturn(null);
		$this->messageParser->method('createMessage')->willReturn($message);

		// "0" is not a message: asking for it fails with an InvalidArgumentException
		$this->chatManager->expects($this->never())->method('getComment');

		$items = $this->widget->getItemsV2('user');

		$this->assertCount(1, $items->getItems());
		$this->assertSame('No unread mentions', $items->getHalfEmptyContentMessage());
	}
}

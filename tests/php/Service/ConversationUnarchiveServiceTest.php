<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ConversationUnarchiveService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Settings\UserPreference;
use OCP\Comments\IComment;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ConversationUnarchiveServiceTest extends TestCase {
	protected AttendeeMapper&MockObject $attendeeMapper;
	protected ParticipantService&MockObject $participantService;
	protected IConfig&MockObject $serverConfig;
	protected ConversationUnarchiveService $service;

	public function setUp(): void {
		parent::setUp();

		$this->attendeeMapper = $this->createMock(AttendeeMapper::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->serverConfig = $this->createMock(IConfig::class);
		$this->service = new ConversationUnarchiveService(
			$this->attendeeMapper,
			$this->participantService,
			$this->serverConfig,
		);
	}

	protected function createAttendee(int $id, string $userId): Attendee {
		$attendee = new Attendee();
		$attendee->setId($id);
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId($userId);
		$attendee->setArchived(true);
		return $attendee;
	}

	protected function createRoom(): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getId')->willReturn(42);
		return $room;
	}

	/**
	 * @param list<array{type: string, id: string}> $mentions
	 */
	protected function createComment(array $mentions = []): IComment&MockObject {
		$comment = $this->createMock(IComment::class);
		$comment->method('getMentions')->willReturn($mentions);
		return $comment;
	}

	protected function createSender(Attendee $attendee): Participant&MockObject {
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);
		return $participant;
	}

	public function testNoArchivedAttendees(): void {
		$this->attendeeMapper->method('getArchivedActorsByType')
			->with(42, Attendee::ACTOR_USERS)
			->willReturn([]);
		$this->serverConfig->expects($this->never())
			->method('getUserValueForUsers');
		$this->participantService->expects($this->never())
			->method('unarchiveAttendeesByIds');

		$this->service->unarchiveAfterMessage($this->createRoom(), $this->createComment(), null, false, null);
	}

	public static function dataUnarchiveAfterMessage(): array {
		$never = UserPreference::CONVERSATIONS_UNARCHIVE_NEVER;
		$mention = UserPreference::CONVERSATIONS_UNARCHIVE_MENTION;
		$always = UserPreference::CONVERSATIONS_UNARCHIVE_ALWAYS;
		$directMention = [['type' => 'user', 'id' => 'bob']];
		$allMention = [['type' => 'call', 'id' => 'token']];

		return [
			'no setting keeps archived' => [[], [], false, null, []],
			'never keeps archived on mention' => [['bob' => $never], $directMention, false, null, []],
			'mention: plain message keeps archived' => [['bob' => $mention], [], false, null, []],
			'mention: direct mention unarchives' => [['bob' => $mention], $directMention, false, null, [2]],
			'mention: @all unarchives' => [['bob' => $mention], $allMention, false, null, [2]],
			'mention: reply to own message unarchives' => [['bob' => $mention], [], false, 'bob', [2]],
			'mention: silent mention keeps archived' => [['bob' => $mention], $directMention, true, null, []],
			'always: plain message unarchives' => [['bob' => $always], [], false, null, [2]],
			'always: silent message keeps archived' => [['bob' => $always], [], true, null, []],
			'mixed settings' => [['alice' => $always, 'bob' => $mention], [], false, null, [1]],
			'mixed settings with mention' => [['alice' => $always, 'bob' => $mention], $directMention, false, null, [1, 2]],
		];
	}

	/**
	 * @param array<string, string> $modes
	 * @param list<array{type: string, id: string}> $mentions
	 * @param list<int> $expectedAttendeeIds
	 */
	#[DataProvider('dataUnarchiveAfterMessage')]
	public function testUnarchiveAfterMessage(array $modes, array $mentions, bool $silent, ?string $replyToUserId, array $expectedAttendeeIds): void {
		$this->attendeeMapper->method('getArchivedActorsByType')
			->with(42, Attendee::ACTOR_USERS)
			->willReturn([
				$this->createAttendee(1, 'alice'),
				$this->createAttendee(2, 'bob'),
			]);
		$this->serverConfig->method('getUserValueForUsers')
			->with('spreed', UserPreference::CONVERSATIONS_UNARCHIVE, ['alice', 'bob'])
			->willReturn($modes);

		$parent = null;
		if ($replyToUserId !== null) {
			$parent = $this->createMock(IComment::class);
			$parent->method('getActorType')->willReturn(Attendee::ACTOR_USERS);
			$parent->method('getActorId')->willReturn($replyToUserId);
		}

		$this->participantService->expects($this->once())
			->method('unarchiveAttendeesByIds')
			->with($expectedAttendeeIds);

		$this->service->unarchiveAfterMessage($this->createRoom(), $this->createComment($mentions), null, $silent, $parent);
	}

	public static function dataOwnMessage(): array {
		return [
			'own message unarchives with mention mode' => [UserPreference::CONVERSATIONS_UNARCHIVE_MENTION, true],
			'own message unarchives with always mode' => [UserPreference::CONVERSATIONS_UNARCHIVE_ALWAYS, true],
			'own message keeps archived with never mode' => [UserPreference::CONVERSATIONS_UNARCHIVE_NEVER, false],
		];
	}

	#[DataProvider('dataOwnMessage')]
	public function testOwnMessage(string $mode, bool $expectUnarchive): void {
		$senderAttendee = $this->createAttendee(1, 'alice');
		$this->attendeeMapper->method('getArchivedActorsByType')
			->willReturn([$senderAttendee]);
		$this->serverConfig->method('getUserValueForUsers')
			->willReturn(['alice' => $mode]);

		$this->participantService->expects($this->once())
			->method('unarchiveAttendeesByIds')
			->with($expectUnarchive ? [1] : []);

		// Own messages count even when sent silently
		$this->service->unarchiveAfterMessage($this->createRoom(), $this->createComment(), $this->createSender($senderAttendee), true, null);
	}
}

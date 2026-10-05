<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OC\Comments\Comment;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Config;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\ProxyCacheMessage;
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
	protected Config&MockObject $talkConfig;
	protected UserConverter&MockObject $userConverter;
	protected ConversationUnarchiveService $service;

	public function setUp(): void {
		parent::setUp();

		$this->attendeeMapper = $this->createMock(AttendeeMapper::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->serverConfig = $this->createMock(IConfig::class);
		$this->talkConfig = $this->createMock(Config::class);
		$this->userConverter = $this->createMock(UserConverter::class);
		$this->service = new ConversationUnarchiveService(
			$this->attendeeMapper,
			$this->participantService,
			$this->serverConfig,
			$this->talkConfig,
			$this->userConverter,
		);
	}

	protected function createAttendee(int $id, string $userId, bool $archived = true): Attendee {
		$attendee = new Attendee();
		$attendee->setId($id);
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId($userId);
		$attendee->setArchived($archived);
		return $attendee;
	}

	protected function createRoom(int $type = Room::TYPE_GROUP): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getId')->willReturn(42);
		$room->method('getToken')->willReturn('localtoken');
		$room->method('getType')->willReturn($type);
		return $room;
	}

	/**
	 * @param list<array{type: string, id: string}> $mentions
	 */
	protected function createComment(array $mentions = [], string $actorId = 'carol'): IComment&MockObject {
		$comment = $this->createMock(IComment::class);
		$comment->method('getActorType')->willReturn(Attendee::ACTOR_USERS);
		$comment->method('getActorId')->willReturn($actorId);
		$comment->method('getVerb')->willReturn(ChatManager::VERB_MESSAGE);
		$comment->method('getMentions')->willReturn($mentions);
		return $comment;
	}

	protected function createParticipant(Attendee $attendee): Participant&MockObject {
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

		$this->service->unarchiveAfterMessage($this->createRoom(), $this->createComment(), null);
	}

	public static function dataUnarchiveAfterMessage(): array {
		$never = UserPreference::CONVERSATIONS_UNARCHIVE_NEVER;
		$mention = UserPreference::CONVERSATIONS_UNARCHIVE_MENTION;
		$always = UserPreference::CONVERSATIONS_UNARCHIVE_ALWAYS;
		$directMention = [['type' => 'user', 'id' => 'bob']];
		$allMention = [['type' => 'user', 'id' => 'all']];
		$oneToOne = Room::TYPE_ONE_TO_ONE;

		return [
			'no setting keeps archived' => [[], [], null, []],
			'never: one-to-one plain message keeps archived' => [['bob' => $never], [], null, [], $oneToOne],
			'mention: one-to-one plain message unarchives' => [['bob' => $mention], [], null, [2], $oneToOne],
			'never keeps archived on mention' => [['bob' => $never], $directMention, null, []],
			'mention: plain message keeps archived' => [['bob' => $mention], [], null, []],
			'mention: direct mention unarchives' => [['bob' => $mention], $directMention, null, [2]],
			'mention: @all unarchives' => [['bob' => $mention], $allMention, null, [2]],
			'mention: reply to own message unarchives' => [['bob' => $mention], [], 'bob', [2]],
			'mention: reply to someone else keeps archived' => [['bob' => $mention], [], 'alice', []],
			'always: plain message unarchives' => [['bob' => $always], [], null, [2]],
			'mixed settings' => [['alice' => $always, 'bob' => $mention], [], null, [1]],
			'mixed settings with mention' => [['alice' => $always, 'bob' => $mention], $directMention, null, [1, 2]],
		];
	}

	/**
	 * @param array<string, string> $modes
	 * @param list<array{type: string, id: string}> $mentions
	 * @param list<int> $expectedAttendeeIds
	 */
	#[DataProvider('dataUnarchiveAfterMessage')]
	public function testUnarchiveAfterMessage(array $modes, array $mentions, ?string $replyToUserId, array $expectedAttendeeIds, int $roomType = Room::TYPE_GROUP): void {
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

		$this->service->unarchiveAfterMessage($this->createRoom($roomType), $this->createComment($mentions), $parent);
	}

	public static function dataOwnMessage(): array {
		return [
			'mention mode' => [UserPreference::CONVERSATIONS_UNARCHIVE_MENTION],
			'always mode' => [UserPreference::CONVERSATIONS_UNARCHIVE_ALWAYS],
		];
	}

	#[DataProvider('dataOwnMessage')]
	public function testOwnMessageKeepsArchived(string $mode): void {
		$this->attendeeMapper->method('getArchivedActorsByType')
			->willReturn([$this->createAttendee(1, 'alice'), $this->createAttendee(2, 'bob')]);
		$this->serverConfig->method('getUserValueForUsers')
			->willReturn(['alice' => $mode, 'bob' => $mode]);

		// Only bob is unarchived, even when the sender mentions themselves
		$this->participantService->expects($this->once())
			->method('unarchiveAttendeesByIds')
			->with([2]);

		$comment = $this->createComment([['type' => 'user', 'id' => 'all']], 'alice');
		$this->service->unarchiveAfterMessage($this->createRoom(), $comment, null);
	}

	public static function dataParsedMentions(): array {
		return [
			'plain message keeps archived' => ['Hello', []],
			'direct mention unarchives' => ['Hello @bob', [2]],
			'@all unarchives' => ['Hello @all', [2]],
			'mention of someone else keeps archived' => ['Hello @carol', []],
		];
	}

	/**
	 * Mentions are parsed from a real comment to cover their actual shape
	 *
	 * @param list<int> $expectedAttendeeIds
	 */
	#[DataProvider('dataParsedMentions')]
	public function testParsedMentions(string $message, array $expectedAttendeeIds): void {
		$this->attendeeMapper->method('getArchivedActorsByType')
			->willReturn([$this->createAttendee(2, 'bob')]);
		$this->serverConfig->method('getUserValueForUsers')
			->willReturn(['bob' => UserPreference::CONVERSATIONS_UNARCHIVE_MENTION]);

		$this->participantService->expects($this->once())
			->method('unarchiveAttendeesByIds')
			->with($expectedAttendeeIds);

		$comment = new Comment();
		$comment->setActor(Attendee::ACTOR_USERS, 'alice');
		$comment->setVerb(ChatManager::VERB_MESSAGE);
		$comment->setMessage($message, ChatManager::MAX_CHAT_LENGTH);
		$this->service->unarchiveAfterMessage($this->createRoom(), $comment, null);
	}

	public static function dataSharedObject(): array {
		$mention = UserPreference::CONVERSATIONS_UNARCHIVE_MENTION;
		$always = UserPreference::CONVERSATIONS_UNARCHIVE_ALWAYS;
		$file = ['message' => 'file_shared', 'parameters' => ['share' => '1', 'metaData' => []]];
		$fileWithCaption = ['message' => 'file_shared', 'parameters' => ['share' => '1', 'metaData' => ['caption' => 'Look @bob']]];
		$poll = ['message' => 'object_shared', 'parameters' => ['objectType' => 'talk-poll', 'objectId' => '1']];

		return [
			'always: file unarchives' => [$always, $file, [2]],
			'always: poll unarchives' => [$always, $poll, [2]],
			'mention: file keeps archived' => [$mention, $file, []],
			'mention: poll keeps archived' => [$mention, $poll, []],
			'mention: file with caption mention unarchives' => [$mention, $fileWithCaption, [2]],
		];
	}

	/**
	 * @param array<string, mixed> $message
	 * @param list<int> $expectedAttendeeIds
	 */
	#[DataProvider('dataSharedObject')]
	public function testSharedObject(string $mode, array $message, array $expectedAttendeeIds): void {
		$this->attendeeMapper->method('getArchivedActorsByType')
			->willReturn([$this->createAttendee(2, 'bob')]);
		$this->serverConfig->method('getUserValueForUsers')
			->willReturn(['bob' => $mode]);

		$this->participantService->expects($this->once())
			->method('unarchiveAttendeesByIds')
			->with($expectedAttendeeIds);

		$comment = new Comment();
		$comment->setActor(Attendee::ACTOR_USERS, 'alice');
		$comment->setVerb(ChatManager::VERB_OBJECT_SHARED);
		$comment->setMessage(json_encode($message, JSON_THROW_ON_ERROR), ChatManager::MAX_CHAT_LENGTH);
		$this->service->unarchiveAfterMessage($this->createRoom(), $comment, null);
	}

	/**
	 * @param list<array{type: string, id: string, server?: string}> $parameters
	 * @param array<string, mixed> $metaData
	 */
	protected function createProxyMessage(string $actorType, string $actorId, array $parameters = [], array $metaData = [], string $systemMessage = ''): ProxyCacheMessage {
		$message = new ProxyCacheMessage();
		$message->setActorType($actorType);
		$message->setActorId($actorId);
		$message->setSystemMessage($systemMessage);
		$message->setMessageParameters(json_encode($parameters, JSON_THROW_ON_ERROR));
		$message->setMetaData(json_encode($metaData, JSON_THROW_ON_ERROR));
		return $message;
	}

	public static function dataUnarchiveAfterFederatedMessage(): array {
		$never = UserPreference::CONVERSATIONS_UNARCHIVE_NEVER;
		$mention = UserPreference::CONVERSATIONS_UNARCHIVE_MENTION;
		$always = UserPreference::CONVERSATIONS_UNARCHIVE_ALWAYS;
		$remote = [Attendee::ACTOR_FEDERATED_USERS, 'carol@remote.tld'];
		$own = [Attendee::ACTOR_USERS, 'bob'];
		$directMention = [['type' => 'user', 'id' => 'bob']];
		$remoteUserMention = [['type' => 'user', 'id' => 'bob', 'server' => 'https://other.tld']];
		$allMention = [['type' => 'call', 'id' => 'localtoken']];
		$reply = [ProxyCacheMessage::METADATA_REPLY_TO_ACTOR_TYPE => Attendee::ACTOR_FEDERATED_USERS, ProxyCacheMessage::METADATA_REPLY_TO_ACTOR_ID => 'bob@local.tld'];

		return [
			'never keeps archived' => [$never, $remote, $directMention, [], '', true, false],
			'not archived is untouched' => [$always, $remote, [], [], '', false, false],
			'mention: plain message keeps archived' => [$mention, $remote, [], [], '', true, false],
			'mention: direct mention unarchives' => [$mention, $remote, $directMention, [], '', true, true],
			'mention: same user id on other server is not a mention' => [$mention, $remote, $remoteUserMention, [], '', true, false],
			'mention: @all unarchives' => [$mention, $remote, $allMention, [], '', true, true],
			'mention: reply unarchives' => [$mention, $remote, [], $reply, '', true, true],
			'mention: silent mention unarchives' => [$mention, $remote, $directMention, ['silent' => true], '', true, true],
			'mention: own message keeps archived' => [$mention, $own, $allMention, [], '', true, false],
			'always: plain message unarchives' => [$always, $remote, [], [], '', true, true],
			'always: silent message unarchives' => [$always, $remote, [], ['silent' => true], '', true, true],
			'always: own message keeps archived' => [$always, $own, [], [], '', true, false],
			'always: system message keeps archived' => [$always, $remote, [], [], 'call_started', true, false],
		];
	}

	/**
	 * @param array{0: string, 1: string} $actor
	 * @param list<array{type: string, id: string, server?: string}> $parameters
	 * @param array<string, mixed> $metaData
	 */
	#[DataProvider('dataUnarchiveAfterFederatedMessage')]
	public function testUnarchiveAfterFederatedMessage(string $mode, array $actor, array $parameters, array $metaData, string $systemMessage, bool $archived, bool $expectUnarchive): void {
		$attendee = $this->createAttendee(2, 'bob', $archived);
		$participant = $this->createParticipant($attendee);
		$room = $this->createRoom();

		$this->talkConfig->method('getConversationsUnarchive')
			->with('bob')
			->willReturn($mode);
		$this->userConverter->method('convertTypeAndId')
			->with($room, Attendee::ACTOR_FEDERATED_USERS, 'bob@local.tld')
			->willReturn(['type' => Attendee::ACTOR_USERS, 'id' => 'bob']);

		$this->participantService->expects($expectUnarchive ? $this->once() : $this->never())
			->method('unarchiveConversation')
			->with($participant);

		$this->service->unarchiveAfterFederatedMessage(
			$room,
			$participant,
			$this->createProxyMessage($actor[0], $actor[1], $parameters, $metaData, $systemMessage),
		);
	}
}

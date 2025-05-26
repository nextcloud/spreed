<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Chat\Parser;

use OCA\Talk\Chat\Parser\UserMention;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\GuestManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCP\App\IAppManager;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class UserMentionTest extends TestCase {
	protected IAppManager&MockObject $appManager;
	protected ICommentsManager&MockObject $commentsManager;
	protected IUserManager&MockObject $userManager;
	protected IGroupManager&MockObject $groupManager;
	protected GuestManager&MockObject $guestManager;
	protected AvatarService&MockObject $avatarService;
	protected ICloudIdManager&MockObject $cloudIdManager;
	protected ParticipantService&MockObject $participantService;
	protected IL10N&MockObject $l;

	protected ?UserMention $parser = null;

	public function setUp(): void {
		parent::setUp();

		$this->appManager = $this->createMock(IAppManager::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->guestManager = $this->createMock(GuestManager::class);
		$this->avatarService = $this->createMock(AvatarService::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->l = $this->createMock(IL10N::class);

		$this->parser = new UserMention(
			$this->appManager,
			$this->commentsManager,
			$this->userManager,
			$this->groupManager,
			$this->guestManager,
			$this->avatarService,
			$this->cloudIdManager,
			$this->participantService,
			$this->l,
		);
	}

	/**
	 * @param array $mentions
	 * @param array|null $metadata
	 * @return MockObject|IComment
	 */
	private function newComment(array $mentions, ?array $metadata = null): IComment {
		$comment = $this->createMock(IComment::class);

		$comment->method('getMentions')->willReturn($mentions);

		if ($metadata !== null) {
			$comment->method('getMetaData')->willReturn($metadata);
		}

		return $comment;
	}

	public function testGetRichMessageWithoutEnrichableReferences(): void {
		$comment = $this->newComment([]);

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		/** @var IL10N&MockObject $l */
		$l = $this->createMock(IL10N::class);
		$chatMessage = new Message($room, $participant, $comment, $l);
		$chatMessage->setMessage('Message without enrichable references', []);

		self::invokePrivate($this->parser, 'parseMessage', [$chatMessage]);

		$this->assertEquals('Message without enrichable references', $chatMessage->getMessage());
		$this->assertEquals([], $chatMessage->getMessageParameters());
	}

	public function testGetRichMessageWithSingleMention(): void {
		$mentions = [
			['type' => 'user', 'id' => 'testUser'],
		];
		$comment = $this->newComment($mentions);

		$this->commentsManager->expects($this->once())
			->method('resolveDisplayName')
			->with('user', 'testUser')
			->willReturn('testUser display name');

		$this->userManager->expects($this->once())
			->method('getDisplayName')
			->with('testUser')
			->willReturn('testUser display name');

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		/** @var IL10N&MockObject $l */
		$l = $this->createMock(IL10N::class);
		$chatMessage = new Message($room, $participant, $comment, $l);
		$chatMessage->setMessage('Mention to @testUser', []);

		self::invokePrivate($this->parser, 'parseMessage', [$chatMessage]);

		$expectedMessageParameters = [
			'mention-user1' => [
				'type' => 'user',
				'id' => 'testUser',
				'name' => 'testUser display name',
				'mention-id' => 'testUser',
			]
		];

		$this->assertEquals('Mention to {mention-user1}', $chatMessage->getMessage());
		$this->assertEquals($expectedMessageParameters, $chatMessage->getMessageParameters());
	}

	public function testGetRichMessageWithDuplicatedMention(): void {
		$mentions = [
			['type' => 'user', 'id' => 'testUser'],
		];
		$comment = $this->newComment($mentions);

		$this->commentsManager->expects($this->once())
			->method('resolveDisplayName')
			->with('user', 'testUser')
			->willReturn('testUser display name');

		$this->userManager->expects($this->once())
			->method('getDisplayName')
			->with('testUser')
			->willReturn('testUser display name');

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		/** @var IL10N&MockObject $l */
		$l = $this->createMock(IL10N::class);
		$chatMessage = new Message($room, $participant, $comment, $l);
		$chatMessage->setMessage('Mention to @testUser and @testUser again', []);

		self::invokePrivate($this->parser, 'parseMessage', [$chatMessage]);

		$expectedMessageParameters = [
			'mention-user1' => [
				'type' => 'user',
				'id' => 'testUser',
				'name' => 'testUser display name',
				'mention-id' => 'testUser',
			]
		];

		$this->assertEquals('Mention to {mention-user1} and {mention-user1} again', $chatMessage->getMessage());
		$this->assertEquals($expectedMessageParameters, $chatMessage->getMessageParameters());
	}

	public static function dataGetRichMessageWithMentionsFullyIncludedInOtherMentions(): array {
		// Based on valid characters from server/lib/private/User/Manager.php
		return [
			['testUser', 'testUser1', false],
			['testUser', 'testUser1', true],
			['testUser', 'testUser_1', false],
			['testUser', 'testUser_1', true],
			['testUser', 'testUser.1', false],
			['testUser', 'testUser.1', true],
			['testUser', 'testUser@1', false],
			['testUser', 'testUser@1', true],
			['testUser', 'testUser-1', false],
			['testUser', 'testUser-1', true],
			['testUser', 'testUser\'1', false],
			['testUser', 'testUser\'1', true],
		];
	}

	#[DataProvider('dataGetRichMessageWithMentionsFullyIncludedInOtherMentions')]
	public function testGetRichMessageWithMentionsFullyIncludedInOtherMentions(string $baseId, string $longerId, bool $quoted): void {
		$mentions = [
			['type' => 'user', 'id' => $baseId],
			['type' => 'user', 'id' => $longerId],
		];
		$comment = $this->newComment($mentions);

		$this->commentsManager->expects($this->exactly(2))
			->method('resolveDisplayName')
			->willReturnCallback(function ($type, $id) {
				return $id . ' display name';
			});

		$this->userManager->expects($this->exactly(2))
			->method('getDisplayName')
			->willReturnMap([
				[$longerId, $longerId . ' display name'],
				[$baseId, $baseId . ' display name']
			]);

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		/** @var IL10N&MockObject $l */
		$l = $this->createMock(IL10N::class);
		$chatMessage = new Message($room, $participant, $comment, $l);
		if ($quoted) {
			$chatMessage->setMessage('Mention to @"' . $baseId . '" and @"' . $longerId . '"', []);
		} else {
			$chatMessage->setMessage('Mention to @' . $baseId . ' and @' . $longerId, []);
		}

		self::invokePrivate($this->parser, 'parseMessage', [$chatMessage]);

		$expectedMessageParameters = [
			'mention-user1' => [
				'type' => 'user',
				'id' => $longerId,
				'name' => $longerId . ' display name',
				'mention-id' => $longerId,
			],
			'mention-user2' => [
				'type' => 'user',
				'id' => $baseId,
				'name' => $baseId . ' display name',
				'mention-id' => $baseId,
			],
		];

		$this->assertEquals('Mention to {mention-user2} and {mention-user1}', $chatMessage->getMessage());
		$this->assertEquals($expectedMessageParameters, $chatMessage->getMessageParameters());
	}

	public function testGetRichMessageWithSeveralMentions(): void {
		$mentions = [
			['type' => 'user', 'id' => 'testUser1'],
			['type' => 'user', 'id' => 'testUser2'],
			['type' => 'user', 'id' => 'testUser3']
		];
		$comment = $this->newComment($mentions);

		$this->commentsManager->expects($this->exactly(3))
			->method('resolveDisplayName')
			->willReturnMap([
				['user', 'testUser1', 'testUser1 display name'],
				['user', 'testUser2', 'testUser2 display name'],
				['user', 'testUser3', 'testUser3 display name'],
			]);

		$this->userManager->expects($this->exactly(3))
			->method('getDisplayName')
			->willReturnMap([
				['testUser1', 'testUser1 display name'],
				['testUser2', 'testUser2 display name'],
				['testUser3', 'testUser3 display name'],
			]);

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		/** @var IL10N&MockObject $l */
		$l = $this->createMock(IL10N::class);
		$chatMessage = new Message($room, $participant, $comment, $l);
		$chatMessage->setMessage('Mention to @testUser1, @testUser2, @testUser1 again and @testUser3', []);

		self::invokePrivate($this->parser, 'parseMessage', [$chatMessage]);

		$expectedMessageParameters = [
			'mention-user1' => [
				'type' => 'user',
				'id' => 'testUser1',
				'name' => 'testUser1 display name',
				'mention-id' => 'testUser1',
			],
			'mention-user2' => [
				'type' => 'user',
				'id' => 'testUser2',
				'name' => 'testUser2 display name',
				'mention-id' => 'testUser2',
			],
			'mention-user3' => [
				'type' => 'user',
				'id' => 'testUser3',
				'name' => 'testUser3 display name',
				'mention-id' => 'testUser3',
			]
		];

		$this->assertEquals('Mention to {mention-user1}, {mention-user2}, {mention-user1} again and {mention-user3}', $chatMessage->getMessage());
		$this->assertEquals($expectedMessageParameters, $chatMessage->getMessageParameters());
	}

	public function testGetRichMessageWithNonExistingUserMention(): void {
		$mentions = [
			['type' => 'user', 'id' => 'me'],
			['type' => 'user', 'id' => 'testUser'],
		];
		$comment = $this->newComment($mentions);

		$this->commentsManager->expects($this->once())
			->method('resolveDisplayName')
			->with('user', 'testUser')
			->willReturn('testUser display name');

		$this->userManager->expects($this->exactly(2))
			->method('getDisplayName')
			->willReturnMap([
				['me', null],
				['testUser', 'testUser display name'],
			]);

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		/** @var IL10N&MockObject $l */
		$l = $this->createMock(IL10N::class);
		$chatMessage = new Message($room, $participant, $comment, $l);
		$chatMessage->setMessage('Mention @me to @testUser', []);

		self::invokePrivate($this->parser, 'parseMessage', [$chatMessage]);

		$expectedMessageParameters = [
			'mention-user1' => [
				'type' => 'user',
				'id' => 'testUser',
				'name' => 'testUser display name',
				'mention-id' => 'testUser',
			]
		];

		$this->assertEquals('Mention @me to {mention-user1}', $chatMessage->getMessage());
		$this->assertEquals($expectedMessageParameters, $chatMessage->getMessageParameters());
	}

	public function testGetRichMessageWhenDisplayNameCanNotBeResolved(): void {
		$mentions = [
			['type' => 'user', 'id' => 'testUser'],
		];
		$comment = $this->newComment($mentions);

		$this->commentsManager->expects($this->once())
			->method('resolveDisplayName')
			->willThrowException(new \OutOfBoundsException());

		$this->userManager->expects($this->once())
			->method('getDisplayName')
			->with('testUser')
			->willReturn('existing user but does not resolve later');

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		/** @var IL10N&MockObject $l */
		$l = $this->createMock(IL10N::class);
		$chatMessage = new Message($room, $participant, $comment, $l);
		$chatMessage->setMessage('Mention to @testUser', []);

		self::invokePrivate($this->parser, 'parseMessage', [$chatMessage]);

		$expectedMessageParameters = [
			'mention-user1' => [
				'type' => 'user',
				'id' => 'testUser',
				'name' => '',
				'mention-id' => 'testUser',
			]
		];

		$this->assertEquals('Mention to {mention-user1}', $chatMessage->getMessage());
		$this->assertEquals($expectedMessageParameters, $chatMessage->getMessageParameters());
	}

	public function testGetRichMessageWithAtAll(): void {
		$mentions = [
			['type' => 'user', 'id' => 'all'],
		];
		$metadata = [
			Message::METADATA_CAN_MENTION_ALL => true,
		];
		$comment = $this->newComment($mentions, $metadata);

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		$room->expects($this->once())
			->method('getType')
			->willReturn(Room::TYPE_GROUP);
		$room->expects($this->once())
			->method('getToken')
			->willReturn('token');
		$room->expects($this->once())
			->method('getDisplayName')
			->willReturn('name');
		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		/** @var IL10N&MockObject $l */
		$l = $this->createMock(IL10N::class);
		$chatMessage = new Message($room, $participant, $comment, $l);
		$chatMessage->setMessage('Mention to @all', []);

		$this->avatarService->method('getAvatarUrl')
			->with($room)
			->willReturn('getAvatarUrl');

		self::invokePrivate($this->parser, 'parseMessage', [$chatMessage]);

		$expectedMessageParameters = [
			'mention-call1' => [
				'type' => 'call',
				'id' => 'token',
				'name' => 'name',
				'call-type' => 'group',
				'icon-url' => 'getAvatarUrl',
				'mention-id' => 'all',
			]
		];

		$this->assertEquals('Mention to {mention-call1}', $chatMessage->getMessage());
		$this->assertEquals($expectedMessageParameters, $chatMessage->getMessageParameters());
	}

	public function testGetRichMessageWithFederatedUserMention(): void {
		$mentions = [
			['type' => 'federated_user', 'id' => 'testUser@example.tld'],
		];
		$comment = $this->newComment($mentions);

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		/** @var IL10N&MockObject $l */
		$l = $this->createMock(IL10N::class);
		$chatMessage = new Message($room, $participant, $comment, $l);
		$chatMessage->setMessage('Mention to @"federated_user/testUser@example.tld"', []);

		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getUser')
			->willReturn('testUser');
		$cloudId->method('getRemote')
			->willReturn('example.tld');
		$cloudId->method('getDisplayId')
			->willReturn('Display Id');
		$this->cloudIdManager->method('resolveCloudId')
			->with('testUser@example.tld')
			->willReturn($cloudId);

		self::invokePrivate($this->parser, 'parseMessage', [$chatMessage]);

		$expectedMessageParameters = [
			'mention-federated-user1' => [
				'type' => 'user',
				'id' => 'testUser',
				'name' => 'Display Id',
				'server' => 'example.tld',
				'mention-id' => 'federated_user/testUser@example.tld',
			]
		];

		$this->assertEquals('Mention to {mention-federated-user1}', $chatMessage->getMessage());
		$this->assertEquals($expectedMessageParameters, $chatMessage->getMessageParameters());
	}

	public function testGetRichMessageWhenAGuestWithoutNameIsMentioned(): void {
		$mentions = [
			['type' => 'guest', 'id' => 'guest/123456'],
		];
		$comment = $this->newComment($mentions);

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		/** @var IL10N&MockObject $l */
		$l = $this->createMock(IL10N::class);

		$this->participantService->method('getParticipantByActor')
			->with($room, Attendee::ACTOR_GUESTS, '123456')
			->willThrowException(new ParticipantNotFoundException());
		$this->l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});

		$chatMessage = new Message($room, $participant, $comment, $l);
		$chatMessage->setMessage('Mention to @"guest/123456"', []);

		self::invokePrivate($this->parser, 'parseMessage', [$chatMessage]);

		$expectedMessageParameters = [
			'mention-guest1' => [
				'type' => 'guest',
				'id' => 'guest/123456',
				'name' => 'Guest',
				'mention-id' => 'guest/123456',
			]
		];

		$this->assertEquals('Mention to {mention-guest1}', $chatMessage->getMessage());
		$this->assertEquals($expectedMessageParameters, $chatMessage->getMessageParameters());
	}

	public function testGetRichMessageWhenAGuestWithoutNameIsMentionedMultipleTimes(): void {
		$mentions = [
			['type' => 'guest', 'id' => 'guest/123456'],
		];
		$comment = $this->newComment($mentions);

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		/** @var IL10N&MockObject $l */
		$l = $this->createMock(IL10N::class);

		$this->participantService->method('getParticipantByActor')
			->with($room, Attendee::ACTOR_GUESTS, '123456')
			->willThrowException(new ParticipantNotFoundException());
		$this->l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});

		$chatMessage = new Message($room, $participant, $comment, $l);
		$chatMessage->setMessage('Mention to @"guest/123456", and again @"guest/123456"', []);

		self::invokePrivate($this->parser, 'parseMessage', [$chatMessage]);

		$expectedMessageParameters = [
			'mention-guest1' => [
				'type' => 'guest',
				'id' => 'guest/123456',
				'name' => 'Guest',
				'mention-id' => 'guest/123456',
			]
		];

		$this->assertEquals('Mention to {mention-guest1}, and again {mention-guest1}', $chatMessage->getMessage());
		$this->assertEquals($expectedMessageParameters, $chatMessage->getMessageParameters());
	}

	public function testGetRichMessageWhenAGuestWithANameIsMentionedMultipleTimes(): void {
		$mentions = [
			['type' => 'guest', 'id' => 'guest/abcdef'],
		];
		$comment = $this->newComment($mentions);

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		/** @var IL10N&MockObject $l */
		$l = $this->createMock(IL10N::class);

		$attendee = Attendee::fromRow([
			'actor_type' => 'guests',
			'actor_id' => 'abcdef',
			'display_name' => 'Name',
		]);
		$participant->method('getAttendee')
			->willReturn($attendee);

		$this->participantService->method('getParticipantByActor')
			->with($room, Attendee::ACTOR_GUESTS, 'abcdef')
			->willReturn($participant);
		$this->l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});

		$chatMessage = new Message($room, $participant, $comment, $l);
		$chatMessage->setMessage('Mention to @"guest/abcdef", and again @"guest/abcdef"', []);

		self::invokePrivate($this->parser, 'parseMessage', [$chatMessage]);

		$expectedMessageParameters = [
			'mention-guest1' => [
				'type' => 'guest',
				'id' => 'guest/abcdef',
				'name' => 'Name',
				'mention-id' => 'guest/abcdef',
			]
		];

		$this->assertEquals('Mention to {mention-guest1}, and again {mention-guest1}', $chatMessage->getMessage());
		$this->assertEquals($expectedMessageParameters, $chatMessage->getMessageParameters());
	}
}

<?php

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Spreed\Tests\php\Chat;

use OCA\Spreed\Chat\Notifier;
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Files\Util;
use OCA\Spreed\Manager;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCP\Comments\IComment;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\IUserManager;

class NotifierTest extends \Test\TestCase {

	/** @var \OCP\Notification\IManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $notificationManager;

	/** @var \OCP\IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;

	/** @var \OCA\Spreed\Manager|\PHPUnit_Framework_MockObject_MockObject */
	protected $manager;

	/** @var \OCA\Spreed\Files\Util|\PHPUnit_Framework_MockObject_MockObject */
	protected $util;

	/** @var \OCA\Spreed\Chat\Notifier */
	protected $notifier;

	public function setUp() {
		parent::setUp();

		$this->notificationManager = $this->createMock(INotificationManager::class);

		$this->userManager = $this->createMock(IUserManager::class);
		$this->userManager
			->method('userExists')
			->will($this->returnCallback(function($userId) {
				if ($userId === 'unknownUser') {
					return false;
				}

				return true;
			}));

		$this->manager = $this->createMock(Manager::class);

		$this->util = $this->createMock(Util::class);

		$this->notifier = new Notifier($this->notificationManager,
									   $this->userManager,
									   $this->manager,
									   $this->util);
	}

	private function newComment($id, $actorType, $actorId, $creationDateTime, $message) {
		// $mentionMatches[0] contains the whole matches, while
		// $mentionMatches[1] contains the matched subpattern.
		$mentionMatches = [];
		preg_match_all('/@([a-zA-Z0-9]+)/', $message, $mentionMatches);

		$mentions = array_map(function($mentionMatch) {
			return [ 'type' => 'user', 'id' => $mentionMatch ];
		}, $mentionMatches[1]);

		$comment = $this->createMock(IComment::class);

		$comment->method('getId')->willReturn($id);
		$comment->method('getObjectId')->willReturn(1234);
		$comment->method('getActorType')->willReturn($actorType);
		$comment->method('getActorId')->willReturn($actorId);
		$comment->method('getCreationDateTime')->willReturn($creationDateTime);
		$comment->method('getMessage')->willReturn($message);
		$comment->method('getMentions')->willReturn($mentions);
		$comment->method('getVerb')->willReturn('comment');

		return $comment;
	}

	private function newNotification($room, IComment $comment) {
		$notification = $this->createMock(INotification::class);

		$notification->expects($this->once())
			->method('setApp')
			->with('spreed')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setObject')
			->with('chat', $room->getToken())
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setSubject')
			->with('mention', [
				'userType' => $comment->getActorType(),
				'userId' => $comment->getActorId(),
			])
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setMessage')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setDateTime')
			->with($comment->getCreationDateTime())
			->willReturnSelf();

		return $notification;
	}

	public function testNotifyMentionedUsers() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'Mention @anotherUser');

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');

		$notification = $this->newNotification($room, $comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setUser')
			->with('anotherUser')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setMessage')
			->with('comment')
			->willReturnSelf();

		$this->manager->expects($this->once())
			->method('getRoomById')
			->with(1234)
			->willReturn($room);

		$participant = $this->createMock(Participant::class);

		$room->expects($this->once())
			->method('getParticipant')
			->with('anotherUser')
			->willReturn($participant);

		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notifier->notifyMentionedUsers($room, $comment, []);
	}

	public function testNotNotifyMentionedUserIfReplyToAuthor() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'Mention @anotherUser');

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');

		$notification = $this->newNotification($room, $comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->never())
			->method('setUser');

		$notification->expects($this->once())
			->method('setMessage')
			->with('comment')
			->willReturnSelf();

		$this->notificationManager->expects($this->never())
			->method('notify');

		$this->notifier->notifyMentionedUsers($room, $comment, ['anotherUser']);
	}

	public function testNotifyMentionedUsersByGuest() {
		$comment = $this->newComment(108, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000016), 'Mention @anotherUser');

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');

		$notification = $this->newNotification($room, $comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setUser')
			->with('anotherUser')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setMessage')
			->with('comment')
			->willReturnSelf();

		$this->manager->expects($this->once())
			->method('getRoomById')
			->with(1234)
			->willReturn($room);

		$participant = $this->createMock(Participant::class);

		$room->expects($this->once())
			->method('getParticipant')
			->with('anotherUser')
			->willReturn($participant);

		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notifier->notifyMentionedUsers($room, $comment, []);
	}

	public function testNotifyMentionedUsersWithLongMessageStartMention() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016),
			'123456789 @anotherUserWithOddLengthName 123456789-123456789-123456789-123456789-123456789-123456789');

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');

		$notification = $this->newNotification($room, $comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setUser')
			->with('anotherUserWithOddLengthName')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setMessage')
			->with('comment')
			->willReturnSelf();

		$this->manager->expects($this->once())
			->method('getRoomById')
			->with(1234)
			->willReturn($room);

		$participant = $this->createMock(Participant::class);

		$room->expects($this->once())
			->method('getParticipant')
			->with('anotherUserWithOddLengthName')
			->willReturn($participant);

		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notifier->notifyMentionedUsers($room, $comment, []);
	}

	public function testNotifyMentionedUsersWithLongMessageMiddleMention() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016),
			'123456789-123456789-123456789-1234 @anotherUserWithOddLengthName 6789-123456789-123456789-123456789');

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');

		$notification = $this->newNotification($room, $comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setUser')
			->with('anotherUserWithOddLengthName')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setMessage')
			->with('comment')
			->willReturnSelf();

		$this->manager->expects($this->once())
			->method('getRoomById')
			->with(1234)
			->willReturn($room);

		$participant = $this->createMock(Participant::class);

		$room->expects($this->once())
			->method('getParticipant')
			->with('anotherUserWithOddLengthName')
			->willReturn($participant);

		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notifier->notifyMentionedUsers($room, $comment, []);
	}

	public function testNotifyMentionedUsersWithLongMessageEndMention() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016),
			'123456789-123456789-123456789-123456789-123456789-123456789 @anotherUserWithOddLengthName 123456789');

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');

		$notification = $this->newNotification($room, $comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setUser')
			->with('anotherUserWithOddLengthName')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setMessage')
			->with('comment')
			->willReturnSelf();

		$this->manager->expects($this->once())
			->method('getRoomById')
			->with(1234)
			->willReturn($room);

		$participant = $this->createMock(Participant::class);

		$room->expects($this->once())
			->method('getParticipant')
			->with('anotherUserWithOddLengthName')
			->willReturn($participant);

		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notifier->notifyMentionedUsers($room, $comment, []);
	}

	public function testNotifyMentionedUsersToSelf() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'Mention @testUser');

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');

		$notification = $this->newNotification($room, $comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$this->notificationManager->expects($this->never())
			->method('notify');

		$this->notifier->notifyMentionedUsers($room, $comment, []);
	}

	public function testNotifyMentionedUsersToUnknownUser() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'Mention @unknownUser');

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');


		$notification = $this->newNotification($room, $comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$this->notificationManager->expects($this->never())
			->method('notify');

		$this->notifier->notifyMentionedUsers($room, $comment, []);
	}

	public function testNotifyMentionedUsersToUserNotInvitedToChat() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'Mention @userNotInOneToOneChat');

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');

		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomById')
			->with(1234)
			->willReturn($room);

		$room->expects($this->once())
			->method('getParticipant')
			->with('userNotInOneToOneChat')
			->will($this->throwException(new ParticipantNotFoundException()));

		$notification = $this->newNotification($room, $comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$this->notificationManager->expects($this->never())
			->method('notify');

		$this->notifier->notifyMentionedUsers($room, $comment, []);
	}

	public function testNotifyMentionedUsersNoMentions() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'No mentions');

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');

		$this->notificationManager->expects($this->never())
			->method('createNotification');

		$this->notificationManager->expects($this->never())
			->method('notify');

		$this->notifier->notifyMentionedUsers($room, $comment, []);
	}

	public function testNotifyMentionedUsersSeveralMentions() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'Mention @anotherUser, and @unknownUser, and @testUser, and @userAbleToJoin');

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');

		$notification = $this->newNotification($room, $comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setMessage')
			->with('comment')
			->willReturnSelf();

		$notification->expects($this->exactly(2))
			->method('setUser')
			->withConsecutive(
				[ 'anotherUser' ],
				[ 'userAbleToJoin' ]
			)
			->willReturnSelf();

		$this->manager->expects($this->exactly(2))
			->method('getRoomById')
			->with(1234)
			->willReturn($room);

		$participant = $this->createMock(Participant::class);

		$room->expects($this->exactly(2))
			->method('getParticipant')
			->withConsecutive(['anotherUser'], ['userAbleToJoin'])
			->willReturn($participant);

		$this->notificationManager->expects($this->exactly(2))
			->method('notify')
			->withConsecutive(
				[ $notification ],
				[ $notification ]
			);

		$this->notifier->notifyMentionedUsers($room, $comment, []);
	}

	public function testRemovePendingNotificationsForRoom() {
		$notification = $this->createMock(INotification::class);

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setApp')
			->with('spreed')
			->willReturnSelf();

		$notification->expects($this->exactly(3))
			->method('setObject')
			->withConsecutive(
				['chat', 'Token123'],
				['room', 'Token123'],
				['call', 'Token123']
			)
			->willReturnSelf();

		$this->notificationManager->expects($this->exactly(3))
			->method('markProcessed')
			->with($notification);

		$this->notifier->removePendingNotificationsForRoom($room);
	}

}

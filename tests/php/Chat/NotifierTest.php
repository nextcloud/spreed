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
use OCP\Comments\IComment;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\IUserManager;

class NotifierTest extends \Test\TestCase {

	/** @var \OCP\Notification\IManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $notificationManager;

	/** @var \OCP\IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;

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

		$this->notifier = new Notifier($this->notificationManager,
									   $this->userManager);
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
		$comment->method('getActorType')->willReturn($actorType);
		$comment->method('getActorId')->willReturn($actorId);
		$comment->method('getCreationDateTime')->willReturn($creationDateTime);
		$comment->method('getMessage')->willReturn($message);
		$comment->method('getMentions')->willReturn($mentions);

		return $comment;
	}

	private function newNotification($comment) {
		$notification = $this->createMock(INotification::class);

		$notification->expects($this->once())
			->method('setApp')
			->with('spreed')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setObject')
			->with('room', $comment->getObjectId())
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setSubject')
			->with('mention', [
				'userType' => $comment->getActorType(),
				'userId' => $comment->getActorId(),
			])
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setDateTime')
			->with($comment->getCreationDateTime())
			->willReturnSelf();

		return $notification;
	}

	public function testNotifyMentionedUsers() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'Mention @anotherUser');

		$notification = $this->newNotification($comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setUser')
			->with('anotherUser')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setMessage')
			->with($comment->getMessage())
			->willReturnSelf();

		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notifier->notifyMentionedUsers($comment);
	}

	public function testNotifyMentionedUsersByGuest() {
		$comment = $this->newComment(108, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000016), 'Mention @anotherUser');

		$notification = $this->newNotification($comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setUser')
			->with('anotherUser')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setMessage')
			->with($comment->getMessage())
			->willReturnSelf();

		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notifier->notifyMentionedUsers($comment);
	}

	public function testNotifyMentionedUsersWithLongMessageStartMention() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016),
			'123456789 @anotherUserWithOddLengthName 123456789-123456789-123456789-123456789-123456789-123456789');

		$notification = $this->newNotification($comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setUser')
			->with('anotherUserWithOddLengthName')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setMessage')
			->with('123456789 @anotherUserWithOddLengthName 123456789-123456789-1234', ['ellipsisEnd'])
			->willReturnSelf();

		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notifier->notifyMentionedUsers($comment);
	}

	public function testNotifyMentionedUsersWithLongMessageMiddleMention() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016),
			'123456789-123456789-123456789-1234 @anotherUserWithOddLengthName 6789-123456789-123456789-123456789');

		$notification = $this->newNotification($comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setUser')
			->with('anotherUserWithOddLengthName')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setMessage')
			->with('89-123456789-1234 @anotherUserWithOddLengthName 6789-123456789-1', ['ellipsisStart', 'ellipsisEnd'])
			->willReturnSelf();

		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notifier->notifyMentionedUsers($comment);
	}

	public function testNotifyMentionedUsersWithLongMessageEndMention() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016),
			'123456789-123456789-123456789-123456789-123456789-123456789 @anotherUserWithOddLengthName 123456789');

		$notification = $this->newNotification($comment);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setUser')
			->with('anotherUserWithOddLengthName')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setMessage')
			->with('6789-123456789-123456789 @anotherUserWithOddLengthName 123456789', ['ellipsisStart'])
			->willReturnSelf();

		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notifier->notifyMentionedUsers($comment);
	}

	public function testNotifyMentionedUsersToSelf() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'Mention @testUser');

		$this->notificationManager->expects($this->never())
			->method('createNotification');

		$this->notificationManager->expects($this->never())
			->method('notify');

		$this->notifier->notifyMentionedUsers($comment);
	}

	public function testNotifyMentionedUsersToUnknownUser() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'Mention @unknownUser');

		$this->notificationManager->expects($this->never())
			->method('createNotification');

		$this->notificationManager->expects($this->never())
			->method('notify');

		$this->notifier->notifyMentionedUsers($comment);
	}

	public function testNotifyMentionedUsersNoMentions() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'No mentions');

		$this->notificationManager->expects($this->never())
			->method('createNotification');

		$this->notificationManager->expects($this->never())
			->method('notify');

		$this->notifier->notifyMentionedUsers($comment);
	}

	public function testNotifyMentionedUsersSeveralMentions() {
		$comment = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'Mention @anotherUser, and @unknownUser, and @testUser, and @userAbleToJoin');

		$anotherUserNotification = $this->newNotification($comment);
		$userAbleToJoinNotification = $this->newNotification($comment);

		$this->notificationManager->expects($this->exactly(2))
			->method('createNotification')
			->will($this->onConsecutiveCalls(
				$anotherUserNotification,
				$userAbleToJoinNotification
			));

		$anotherUserNotification->expects($this->once())
			->method('setUser')
			->with('anotherUser')
			->willReturnSelf();

		$anotherUserNotification->expects($this->once())
			->method('setMessage')
			->with('Mention @anotherUser, and @unknownUser, and @testUser, and @user')
			->willReturnSelf();

		$userAbleToJoinNotification->expects($this->once())
			->method('setUser')
			->with('userAbleToJoin')
			->willReturnSelf();

		$userAbleToJoinNotification->expects($this->once())
			->method('setMessage')
			->with('notherUser, and @unknownUser, and @testUser, and @userAbleToJoin')
			->willReturnSelf();

		$this->notificationManager->expects($this->exactly(2))
			->method('notify')
			->withConsecutive(
				[ $anotherUserNotification ],
				[ $userAbleToJoinNotification ]
			);

		$this->notifier->notifyMentionedUsers($comment);
	}

	public function testRemovePendingNotificationsForRoom() {
		$notification = $this->createMock(INotification::class);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setApp')
			->with('spreed')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setObject')
			->with('room', 'testChatId')
			->willReturnSelf();

		$this->notificationManager->expects($this->once())
			->method('markProcessed')
			->with($notification);

		$this->notifier->removePendingNotificationsForRoom('testChatId');
	}

}

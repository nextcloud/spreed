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

use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Chat\CommentsManager;
use OCA\Spreed\Chat\Notifier;
use OCA\Spreed\Room;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ChatManagerTest extends \Test\TestCase {

	/** @var CommentsManager|ICommentsManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $commentsManager;

	/** @var EventDispatcherInterface|\PHPUnit_Framework_MockObject_MockObject */
	protected $dispatcher;

	/** @var Notifier|\PHPUnit_Framework_MockObject_MockObject */
	protected $notifier;

	/** @var ChatManager */
	protected $chatManager;

	public function setUp() {
		parent::setUp();

		$this->commentsManager = $this->createMock(CommentsManager::class);
		$this->dispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->notifier = $this->createMock(Notifier::class);

		$this->chatManager = new ChatManager($this->commentsManager,
											 $this->dispatcher,
											 $this->notifier);
	}

	private function newComment($id, $actorType, $actorId, $creationDateTime, $message) {
		$comment = $this->createMock(IComment::class);

		$comment->method('getId')->willReturn($id);
		$comment->method('getActorType')->willReturn($actorType);
		$comment->method('getActorId')->willReturn($actorId);
		$comment->method('getCreationDateTime')->willReturn($creationDateTime);
		$comment->method('getMessage')->willReturn($message);

		// Used for equals comparison
		$comment->id = $id;
		$comment->actorType = $actorType;
		$comment->actorId = $actorId;
		$comment->creationDateTime = $creationDateTime;
		$comment->message = $message;

		return $comment;
	}

	public function testSendMessage() {
		$comment = $this->createMock(IComment::class);

		$this->commentsManager->expects($this->once())
			->method('create')
			->with('users', 'testUser', 'chat', 'testChatId')
			->willReturn($comment);

		$comment->expects($this->once())
			->method('setMessage')
			->with('testMessage');

		$creationDateTime = new \DateTime();
		$comment->expects($this->once())
			->method('setCreationDateTime')
			->with($creationDateTime);

		$comment->expects($this->once())
			->method('setVerb')
			->with('comment');

		$this->commentsManager->expects($this->once())
			->method('save')
			->with($comment);

		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn('testChatId');

		$this->notifier->expects($this->once())
			->method('notifyMentionedUsers')
			->with($chat, $comment);

		$this->chatManager->sendMessage($chat, 'users', 'testUser', 'testMessage', $creationDateTime);
	}

	public function testGetHistory() {
		$offset = 1;
		$limit = 42;
		$expected = [
			$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000042), 'testMessage3'),
			$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000023), 'testMessage2'),
			$this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage1')
		];

		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn('testChatId');

		$this->commentsManager->expects($this->once())
			->method('getForObjectSince')
			->with('chat', 'testChatId', $offset, 'desc', $limit)
			->willReturn($expected);

		$comments = $this->chatManager->getHistory($chat, $offset, $limit);

		$this->assertEquals($expected, $comments);
	}

	public function testWaitForNewMessages() {
		$offset = 1;
		$limit = 42;
		$timeout = 23;
		$expected = [
			$this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage1'),
			$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000023), 'testMessage2'),
			$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000042), 'testMessage3'),
		];

		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn('testChatId');

		$this->commentsManager->expects($this->once())
			->method('getForObjectSince')
			->with('chat', 'testChatId', $offset, 'asc', $limit)
			->willReturn($expected);

		$this->notifier->expects($this->once())
			->method('markMentionNotificationsRead')
			->with($chat, 'userId');

		/** @var IUser|\PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('userId');

		$comments = $this->chatManager->waitForNewMessages($chat, $offset, $limit, $timeout, $user);

		$this->assertEquals($expected, $comments);
	}

	public function testWaitForNewMessagesWithWaiting() {
		$offset = 1;
		$limit = 42;
		$timeout = 23;
		$expected = [
			$this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage1'),
			$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000023), 'testMessage2'),
			$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000042), 'testMessage3'),
		];

		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn('testChatId');

		$this->commentsManager->expects($this->exactly(2))
			->method('getForObjectSince')
			->with('chat', 'testChatId', $offset, 'asc', $limit)
			->willReturnOnConsecutiveCalls(
				[],
				$expected
			);

		$this->notifier->expects($this->once())
			->method('markMentionNotificationsRead')
			->with($chat, 'userId');

		/** @var IUser|\PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('userId');

		$comments = $this->chatManager->waitForNewMessages($chat, $offset, $limit, $timeout, $user);

		$this->assertEquals($expected, $comments);
	}

	public function testGetUnreadCount() {
		/** @var Room|MockObject $chat */
		$chat = $this->createMock(Room::class);
		$chat->expects($this->once())
			->method('getId')
			->willReturn(23);

		$this->commentsManager->expects($this->once())
			->method('getNumberOfCommentsForObjectSinceComment')
			->with('chat', 23, 42, 'comment');

		$this->chatManager->getUnreadCount($chat, 42);
	}

	public function testDeleteMessages() {

		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn('testChatId');

		$this->commentsManager->expects($this->once())
			->method('deleteCommentsAtObject')
			->with('chat', 'testChatId');

		$this->notifier->expects($this->once())
			->method('removePendingNotificationsForRoom')
			->with($chat);

		$this->chatManager->deleteMessages($chat);
	}

}

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
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;

class ChatManagerTest extends \Test\TestCase {

	/** @var OCP\Comments\ICommentsManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $commentsManager;

	/** @var \OCA\Spreed\Chat\ChatManager */
	protected $chatManager;

	public function setUp() {
		parent::setUp();

		$this->commentsManager = $this->createMock(ICommentsManager::class);

		$this->chatManager = new ChatManager($this->commentsManager);
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

		$this->chatManager->sendMessage('testChatId', 'users', 'testUser', 'testMessage', $creationDateTime);
	}

	public function testReceiveMessages() {
		$notOlderThan = new \DateTime('@1000000000');

		$offset = 1;
		$this->commentsManager->expects($this->once())
			->method('getNumberOfCommentsForObject')
			->with('chat', 'testChatId', $notOlderThan)
			->willReturn($offset + 2);

		$limit = 0;
		$getForObjectOffset = 0;
		$this->commentsManager->expects($this->once())
			->method('getForObject')
			->with('chat', 'testChatId', $limit, $getForObjectOffset, $notOlderThan)
			->willReturn([
				$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000042), 'testMessage3'),
				$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000023), 'testMessage2'),
				$this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage1')
			]);

		$timeout = 42;
		$comments = $this->chatManager->receiveMessages('testChatId', $timeout, $offset, $notOlderThan);
		$expected = [
				$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000042), 'testMessage3'),
				$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000023), 'testMessage2')
		];

		$this->assertEquals($expected, $comments);
	}

	public function testReceiveMessagesMoreCommentsThanExpected() {
		$notOlderThan = new \DateTime('@1000000000');

		$offset = 1;
		$this->commentsManager->expects($this->once())
			->method('getNumberOfCommentsForObject')
			->with('chat', 'testChatId', $notOlderThan)
			->willReturn($offset + 2);

		// An extra comment was added between the call to
		// getNumberOfCommentsForObject and the call to getForObject
		$limit = 0;
		$getForObjectOffset = 0;
		$this->commentsManager->expects($this->once())
			->method('getForObject')
			->with('chat', 'testChatId', $limit, $getForObjectOffset, $notOlderThan)
			->willReturn([
				$this->newComment(111, 'users', 'testUser', new \DateTime('@' . 1000000108), 'testMessage4'),
				$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000042), 'testMessage3'),
				$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000023), 'testMessage2'),
				$this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage1')
			]);

		$timeout = 42;
		$comments = $this->chatManager->receiveMessages('testChatId', $timeout, $offset, $notOlderThan);
		$expected = [
				$this->newComment(111, 'users', 'testUser', new \DateTime('@' . 1000000108), 'testMessage4'),
				$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000042), 'testMessage3'),
				$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000023), 'testMessage2')
		];

		$this->assertEquals($expected, $comments);
	}

	public function testReceiveMessagesNoOffset() {
		$notOlderThan = new \DateTime('@1000000000');

		$offset = 0;
		$this->commentsManager->expects($this->once())
			->method('getNumberOfCommentsForObject')
			->with('chat', 'testChatId', $notOlderThan)
			->willReturn($offset + 3);

		$limit = 0;
		$getForObjectOffset = 0;
		$this->commentsManager->expects($this->once())
			->method('getForObject')
			->with('chat', 'testChatId', $limit, $getForObjectOffset, $notOlderThan)
			->willReturn([
				$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000042), 'testMessage3'),
				$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000023), 'testMessage2'),
				$this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage1')
			]);

		$timeout = 42;
		$comments = $this->chatManager->receiveMessages('testChatId', $timeout, $offset, $notOlderThan);
		$expected = [
				$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000042), 'testMessage3'),
				$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000023), 'testMessage2'),
				$this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage1')
		];

		$this->assertEquals($expected, $comments);
	}

}

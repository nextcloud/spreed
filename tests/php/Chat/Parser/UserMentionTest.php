<?php
declare(strict_types=1);
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

namespace OCA\Spreed\Tests\php\Chat\Parser;

use OCA\Spreed\Chat\Parser\UserMention;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IUser;
use OCP\IUserManager;

class UserMentionTest extends \Test\TestCase {

	/** @var ICommentsManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $commentsManager;

	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;

	/** @var UserMention */
	protected $richMessageHelper;

	public function setUp() {
		parent::setUp();

		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->richMessageHelper = new UserMention($this->commentsManager, $this->userManager);
	}

	/**
	 * @param string $message
	 * @param array $mentions
	 * @return \PHPUnit\Framework\MockObject\MockObject|IComment
	 */
	private function newComment(string $message, array $mentions): IComment {
		$comment = $this->createMock(IComment::class);

		$comment->method('getMessage')->willReturn($message);
		$comment->method('getMentions')->willReturn($mentions);

		return $comment;
	}

	public function testGetRichMessageWithoutEnrichableReferences() {
		$comment = $this->newComment('Message without enrichable references', []);

		list($message, $messageParameters) = $this->richMessageHelper->parseMessage($comment);

		$this->assertEquals('Message without enrichable references', $message);
		$this->assertEquals([], $messageParameters);
	}

	public function testGetRichMessageWithSingleMention() {
		$mentions = [
			['type'=>'user', 'id'=>'testUser'],
		];
		$comment = $this->newComment('Mention to @testUser', $mentions);

		$this->commentsManager->expects($this->once())
			->method('resolveDisplayName')
			->with('user', 'testUser')
			->willReturn('testUser display name');

		$this->userManager->expects($this->once())
			->method('get')
			->with('testUser')
			->willReturn($this->createMock(IUser::class));

		list($message, $messageParameters) = $this->richMessageHelper->parseMessage($comment);

		$expectedMessageParameters = [
			'mention-user1' => [
				'type' => 'user',
				'id' => 'testUser',
				'name' => 'testUser display name'
			]
		];

		$this->assertEquals('Mention to {mention-user1}', $message);
		$this->assertEquals($expectedMessageParameters, $messageParameters);
	}

	public function testGetRichMessageWithDuplicatedMention() {
		$mentions = [
			['type'=>'user', 'id'=>'testUser'],
		];
		$comment = $this->newComment('Mention to @testUser and @testUser again', $mentions);

		$this->commentsManager->expects($this->once())
			->method('resolveDisplayName')
			->with('user', 'testUser')
			->willReturn('testUser display name');

		$this->userManager->expects($this->once())
			->method('get')
			->with('testUser')
			->willReturn($this->createMock(IUser::class));

		list($message, $messageParameters) = $this->richMessageHelper->parseMessage($comment);

		$expectedMessageParameters = [
			'mention-user1' => [
				'type' => 'user',
				'id' => 'testUser',
				'name' => 'testUser display name'
			]
		];

		$this->assertEquals('Mention to {mention-user1} and {mention-user1} again', $message);
		$this->assertEquals($expectedMessageParameters, $messageParameters);
	}

	public function testGetRichMessageWithSeveralMentions() {
		$mentions = [
			['type'=>'user', 'id'=>'testUser1'],
			['type'=>'user', 'id'=>'testUser2'],
			['type'=>'user', 'id'=>'testUser3']
		];
		$comment = $this->newComment('Mention to @testUser1, @testUser2, @testUser1 again and @testUser3', $mentions);

		$this->commentsManager->expects($this->exactly(3))
			->method('resolveDisplayName')
			->withConsecutive(
				['user', 'testUser1'],
				['user', 'testUser2'],
				['user', 'testUser3']
			)
			->willReturn(
				'testUser1 display name',
				'testUser2 display name',
				'testUser3 display name'
			);

		$this->userManager->expects($this->exactly(3))
			->method('get')
			->withConsecutive(
				['testUser1'],
				['testUser2'],
				['testUser3']
			)
			->willReturn($this->createMock(IUser::class));

		list($message, $messageParameters) = $this->richMessageHelper->parseMessage($comment);

		$expectedMessageParameters = [
			'mention-user1' => [
				'type' => 'user',
				'id' => 'testUser1',
				'name' => 'testUser1 display name'
			],
			'mention-user2' => [
				'type' => 'user',
				'id' => 'testUser2',
				'name' => 'testUser2 display name'
			],
			'mention-user3' => [
				'type' => 'user',
				'id' => 'testUser3',
				'name' => 'testUser3 display name'
			]
		];

		$this->assertEquals('Mention to {mention-user1}, {mention-user2}, {mention-user1} again and {mention-user3}', $message);
		$this->assertEquals($expectedMessageParameters, $messageParameters);
	}

	public function testGetRichMessageWithNonExistingUserMention() {
		$mentions = [
			['type'=>'user', 'id'=>'me'],
			['type'=>'user', 'id'=>'testUser'],
		];
		$comment = $this->newComment('Mention @me to @testUser', $mentions);

		$this->commentsManager->expects($this->once())
			->method('resolveDisplayName')
			->with('user', 'testUser')
			->willReturn('testUser display name');

		$this->userManager->expects($this->at(0))
			->method('get')
			->with('me')
			->willReturn(null);

		$this->userManager->expects($this->at(1))
			->method('get')
			->with('testUser')
			->willReturn($this->createMock(IUser::class));

		list($message, $messageParameters) = $this->richMessageHelper->parseMessage($comment);

		$expectedMessageParameters = [
			'mention-user1' => [
				'type' => 'user',
				'id' => 'testUser',
				'name' => 'testUser display name'
			]
		];

		$this->assertEquals('Mention @me to {mention-user1}', $message);
		$this->assertEquals($expectedMessageParameters, $messageParameters);
	}

	public function testGetRichMessageWhenDisplayNameCanNotBeResolved() {
		$mentions = [
			['type'=>'user', 'id'=>'testUser'],
		];
		$comment = $this->newComment('Mention to @testUser', $mentions);

		$this->commentsManager->expects($this->once())
			->method('resolveDisplayName')
			->willThrowException(new \OutOfBoundsException());

		$this->userManager->expects($this->once())
			->method('get')
			->with('testUser')
			->willReturn($this->createMock(IUser::class));

		list($message, $messageParameters) = $this->richMessageHelper->parseMessage($comment);

		$expectedMessageParameters = [
			'mention-user1' => [
				'type' => 'user',
				'id' => 'testUser',
				'name' => ''
			]
		];

		$this->assertEquals('Mention to {mention-user1}', $message);
		$this->assertEquals($expectedMessageParameters, $messageParameters);
	}

}

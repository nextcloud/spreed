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

use OCA\Spreed\Chat\RichMessageHelper;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;

class RichMessageHelperTest extends \Test\TestCase {

	/** @var \OCP\Comments\ICommentsManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $commentsManager;

	/** @var \OCA\Spreed\Chat\RichMessageHelper */
	protected $richMessageHelper;

	public function setUp() {
		parent::setUp();

		$this->commentsManager = $this->createMock(ICommentsManager::class);

		$this->richMessageHelper = new RichMessageHelper($this->commentsManager);
	}

	private function newComment($message, $mentions) {
		$comment = $this->createMock(IComment::class);

		$comment->method('getMessage')->willReturn($message);
		$comment->method('getMentions')->willReturn($mentions);

		return $comment;
	}

	public function testGetRichMessageWithoutEnrichableReferences() {
		$comment = $this->newComment('Message without enrichable references', []);

		list($message, $messageParameters) = $this->richMessageHelper->getRichMessage($comment);

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

		list($message, $messageParameters) = $this->richMessageHelper->getRichMessage($comment);

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

		list($message, $messageParameters) = $this->richMessageHelper->getRichMessage($comment);

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

		list($message, $messageParameters) = $this->richMessageHelper->getRichMessage($comment);

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

	public function testGetRichMessageWhenDisplayNameCanNotBeResolved() {
		$mentions = [
			['type'=>'user', 'id'=>'testUser'],
		];
		$comment = $this->newComment('Mention to @testUser', $mentions);

		$this->commentsManager->expects($this->once())
			->method('resolveDisplayName')
			->willThrowException(new \OutOfBoundsException());

		list($message, $messageParameters) = $this->richMessageHelper->getRichMessage($comment);

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

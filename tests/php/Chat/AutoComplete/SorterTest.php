<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Tests\php\Chat\AutoComplete;

use OCA\Talk\Chat\AutoComplete\Sorter;
use OCA\Talk\Chat\CommentsManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class SorterTest extends TestCase {
	protected CommentsManager&MockObject $commentsManager;

	protected string $userId;

	protected ?Sorter $sorter = null;

	protected static array $user1 = [
		'label' => 'Seattle',
		'value' => [
			'shareType' => 'user',
			'shareWith' => 'seattle',
		],
	];

	protected static array $user2 = [
		'label' => 'New York',
		'value' => [
			'shareType' => 'user',
			'shareWith' => 'new_york',
		],
	];

	protected static array $user3 = [
		'label' => 'ttle Sea',
		'value' => [
			'shareType' => 'user',
			'shareWith' => 'ttle_sea',
		],
	];

	public function setUp(): void {
		parent::setUp();

		$this->commentsManager = $this->createMock(CommentsManager::class);
		$this->sorter = new Sorter($this->commentsManager);
	}

	public function testGetId(): void {
		$this->assertSame('talk_chat_participants', $this->sorter->getId());
	}

	public static function dataSort(): array {
		return [
			'no user posted' => ['', ['users' => [self::$user1, self::$user2]], [], ['users' => [self::$user1, self::$user2]]],
			'second user posted' => ['', ['users' => [self::$user1, self::$user2]], ['new_york' => new \DateTime('2000-01-01')], ['users' => [self::$user2, self::$user1]]],
			'second user posted later' => ['', ['users' => [self::$user1, self::$user2]], ['seattle' => new \DateTime('2017-01-01'), 'new_york' => new \DateTime('2018-01-01')], ['users' => [self::$user2, self::$user1]]],
			'second user posted earlier' => ['', ['users' => [self::$user1, self::$user2]], ['seattle' => new \DateTime('2018-01-01'), 'new_york' => new \DateTime('2017-01-01')], ['users' => [self::$user1, self::$user2]]],
			'starting match first1' => ['Sea', ['users' => [self::$user1, self::$user3]], [], ['users' => [self::$user1, self::$user3]]],
			'starting match first2' => ['Sea', ['users' => [self::$user3, self::$user1]], [], ['users' => [self::$user1, self::$user3]]],
			'no users' => ['', ['groups' => [self::$user1, self::$user2]], [], ['groups' => [self::$user1, self::$user2]]],
		];
	}

	/**
	 * @dataProvider dataSort
	 */
	public function testSort(string $search, array $toSort, array $comments, array $expected): void {
		$this->commentsManager->expects(isset($toSort['users']) ? $this->once() : $this->never())
			->method('getLastCommentDateByActor')
			->with('chat', '23', 'comment', 'users', $this->anything())
			->willReturn($comments);

		$this->sorter->sort($toSort, [
			'itemType' => 'chat',
			'itemId' => '23',
			'search' => $search,
		]);
		$this->assertSame($expected, $toSort);
	}
}

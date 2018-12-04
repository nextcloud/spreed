<?php
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

namespace OCA\Spreed\Tests\php\Chat;

use OCA\Spreed\Chat\AutoComplete\SearchPlugin;
use OCA\Spreed\Files\Util;
use OCA\Spreed\Room;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\IUser;
use OCP\IUserManager;

class SearchPluginTest extends \Test\TestCase {

	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;

	/** @var Util|\PHPUnit_Framework_MockObject_MockObject */
	protected $util;

	/** @var string */
	protected $userId;

	/** @var SearchPlugin */
	protected $plugin;

	public function setUp() {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->util = $this->createMock(Util::class);
		$this->userId = 'current';
	}

	/**
	 * @param string[] $methods
	 * @return SearchPlugin|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getPlugin(array $methods = []) {
		if (empty($methods)) {
			return new SearchPlugin(
				$this->userManager,
				$this->util,
				$this->userId
			);
		}

		return $this->getMockBuilder(SearchPlugin::class)
			->setConstructorArgs([
				$this->userManager,
				$this->util,
				$this->userId,
			])
			->setMethods($methods)
			->getMock();
	}

	public function testSearch() {
		$result = $this->createMock(ISearchResult::class);
		$room = $this->createMock(Room::class);

		$room->expects($this->once())
			->method('getParticipantUserIds')
			->willReturn(['123', 'foo', 'bar']);

		$plugin = $this->getPlugin(['searchUsers']);
		$plugin->setContext(['room' => $room]);
		$plugin->expects($this->once())
			->method('searchUsers')
			->with('fo', ['123', 'foo', 'bar'], $result)
			->willReturnCallback(function($search, $users, $result) {
				array_map(function($user) {
					$this->assertInternalType('string', $user);
				}, $users);
			});

		$plugin->search('fo', 10, 0, $result);
	}

	public function dataSearchUsers() {
		return [
			['test', [], [], [], []],
			['test', ['current', 'foo', 'test', 'test1'], [
				['uid' => 'current', 'name' => 'test'],
				['uid' => 'test', 'name' => 'Te st'],
				['uid' => 'test1', 'name' => 'Te st 1'],
			], [['test1' => '']], [['test' => '']]],
			['test', ['foo', 'bar'], [
				['uid' => 'foo', 'name' => 'Test'],
				['uid' => 'bar', 'name' => 'test One'],
			], [['bar' => 'test One']], [['foo' => 'Test']]],
			['', ['foo', 'bar'], [
			], [['foo' => ''], ['bar' => '']], []],
		];
	}

	/**
	 * @dataProvider dataSearchUsers
	 * @param string $search
	 * @param string[] $userIds
	 * @param array $userNames
	 * @param array $expected
	 * @param array $expectedExact
	 */
	public function testSearchUsers($search, array $userIds, array $userNames, array $expected, array $expectedExact) {
		$result = $this->createMock(ISearchResult::class);

		$userMap = array_map(function($userData) {
			return [$userData['uid'], $this->createUserMock($userData)];
		}, $userNames);

		$this->userManager->expects($this->any())
			->method('get')
			->willReturnMap($userMap);

		$result->expects($this->once())
			->method('addResultSet')
			->with($this->anything(), $expected, $expectedExact);

		$plugin = $this->getPlugin(['createResult']);
		$plugin->expects($this->any())
			->method('createResult')
			->willReturnCallback(function($type, $uid, $name) {
				return [$uid => $name];
			});

		self::invokePrivate($plugin, 'searchUsers', [$search, $userIds, $result]);
	}

	protected function createUserMock(array $userData) {
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn($userData['uid']);
		$user->expects($this->any())
			->method('getDisplayName')
			->willReturn($userData['name']);
		return $user;
	}

	public function dataCreateResult() {
		return [
			['user', 'foo', 'bar', '', ['label' => 'bar', 'value' => ['shareType' => 'user', 'shareWith' => 'foo']]],
			['user', 'test', 'Test', '', ['label' => 'Test', 'value' => ['shareType' => 'user', 'shareWith' => 'test']]],
			['user', 'test', '', 'Test', ['label' => 'Test', 'value' => ['shareType' => 'user', 'shareWith' => 'test']]],
			['user', 'test', '', null, ['label' => 'test', 'value' => ['shareType' => 'user', 'shareWith' => 'test']]],
		];
	}

	/**
	 * @dataProvider dataCreateResult
	 * @param string $type
	 * @param string $uid
	 * @param string $name
	 * @param string $managerName
	 * @param array $expected
	 */
	public function testCreateResult($type, $uid, $name, $managerName, array $expected) {
		if ($managerName !== null) {
			$this->userManager->expects($this->any())
				->method('get')
				->with($uid)
				->willReturn($this->createUserMock(['uid' => $uid, 'name' => $managerName]));
		} else {
			$this->userManager->expects($this->any())
				->method('get')
				->with($uid)
				->willReturn(null);
		}

		$plugin = $this->getPlugin();
		$this->assertEquals($expected, self::invokePrivate($plugin, 'createResult', [$type, $uid, $name]));
	}
}

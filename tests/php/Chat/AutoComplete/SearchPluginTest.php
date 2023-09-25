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

namespace OCA\Talk\Tests\php\Chat\AutoComplete;

use OC\Collaboration\Collaborators\SearchResult;
use OCA\Talk\Chat\AutoComplete\SearchPlugin;
use OCA\Talk\Files\Util;
use OCA\Talk\GuestManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\TalkSession;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class SearchPluginTest extends TestCase {
	/** @var IUserManager|MockObject */
	protected $userManager;
	/** @var GuestManager|MockObject */
	protected $guestManager;
	/** @var IGroupManager|MockObject */
	protected $groupManager;
	/** @var TalkSession|MockObject */
	protected $talkSession;
	/** @var ParticipantService|MockObject */
	protected $participantService;
	/** @var Util|MockObject */
	protected $util;
	/** @var IL10N|MockObject */
	protected $l;
	protected ?string $userId = null;
	protected SearchPlugin $plugin;

	public function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->guestManager = $this->createMock(GuestManager::class);
		$this->talkSession = $this->createMock(TalkSession::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->util = $this->createMock(Util::class);
		$this->userId = 'current';
		$this->l = $this->createMock(IL10N::class);
		$this->l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});
	}

	/**
	 * @param string[] $methods
	 * @return SearchPlugin|MockObject
	 */
	protected function getPlugin(array $methods = []) {
		if (empty($methods)) {
			return new SearchPlugin(
				$this->userManager,
				$this->groupManager,
				$this->guestManager,
				$this->talkSession,
				$this->participantService,
				$this->util,
				$this->userId,
				$this->l
			);
		}

		return $this->getMockBuilder(SearchPlugin::class)
			->setConstructorArgs([
				$this->userManager,
				$this->groupManager,
				$this->guestManager,
				$this->talkSession,
				$this->participantService,
				$this->util,
				$this->userId,
				$this->l,
			])
			->onlyMethods($methods)
			->getMock();
	}

	protected function createParticipantMock(string $uid, string $session = ''): Participant {
		/** @var Participant|MockObject $p */
		$p = $this->createMock(Participant::class);
		$a = Attendee::fromRow([
			'actor_type' => $uid ? 'users' : 'guests',
			'actor_id' => $uid ? $uid : sha1($session),
		]);
		$s = Session::fromRow([
			'session_id' => $session,
		]);
		$p->expects($this->any())
			->method('getAttendee')
			->willReturn($a);
		$p->expects($this->any())
			->method('getSession')
			->willReturn($s);

		$p->expects($this->any())
			->method('isGuest')
			->willReturn($uid === '');

		return $p;
	}

	public function testSearch() {
		$result = $this->createMock(ISearchResult::class);
		$room = $this->createMock(Room::class);

		$this->participantService->expects($this->once())
			->method('getParticipantsForRoom')
			->with($room)
			->willReturn([
				$this->createParticipantMock('123'),
				$this->createParticipantMock('foo'),
				$this->createParticipantMock('', '123456'),
				$this->createParticipantMock('bar'),
				$this->createParticipantMock('', 'abcdef'),
			]);

		$plugin = $this->getPlugin(['searchUsers', 'searchGuests']);
		$plugin->setContext(['room' => $room]);
		$plugin->expects($this->once())
			->method('searchUsers')
			->with('fo', ['123', 'foo', 'bar'], $result)
			->willReturnCallback(function ($search, $users, $result) {
				array_map(function ($user) {
					$this->assertIsString($user);
				}, $users);
			});
		$plugin->expects($this->once())
			->method('searchGuests')
			->with('fo', $this->anything(), $result)
			->willReturnCallback(function ($search, $guests, $result) {
				array_map(function ($guest) {
					$this->assertInstanceOf(Attendee::class, $guest);
				}, $guests);
			});

		$plugin->search('fo', 10, 0, $result);
	}

	public static function dataSearchUsers() {
		return [
			['test', [], [], [], []],
			['test', ['current', 'foo', 'test', 'test1'], [
				['current', 'test'],
				['test', 'Te st'],
				['test1', 'Te st 1'],
			], [['test1' => '']], [['test' => '']]],
			['test', ['foo', 'bar'], [
				['foo', 'Test'],
				['bar', 'test One'],
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

		$this->userManager->expects($this->any())
			->method('getDisplayName')
			->willReturnMap($userNames);

		$result->expects($this->once())
			->method('addResultSet')
			->with($this->anything(), $expected, $expectedExact);

		$plugin = $this->getPlugin(['createResult']);
		$plugin->expects($this->any())
			->method('createResult')
			->willReturnCallback(function ($type, $uid, $name) {
				return [$uid => $name];
			});

		self::invokePrivate($plugin, 'searchUsers', [$search, $userIds, $result]);
	}

	public static function dataSearchGuests() {
		return [
			['test', [], [], []],
			['', ['abcdef' => ''], [['abcdef' => 'Guest']], []],
			['Guest', ['abcdef' => ''], [], [['abcdef' => 'Guest']]],
			['est', ['abcdef' => '', 'foobar' => 'est'], [['abcdef' => 'Guest']], [['foobar' => 'est']]],
			['Ast', ['abcdef' => '', 'foobar' => 'ast'], [], [['foobar' => 'ast']]],
		];
	}

	/**
	 * @dataProvider dataSearchGuests
	 * @param string $search
	 * @param string[] $sessionHashes
	 * @param array $displayNames
	 * @param array $expected
	 * @param array $expectedExact
	 */
	public function testSearchGuests($search, array $guests, array $expected, array $expectedExact) {
		$result = $this->createMock(ISearchResult::class);
		$result->expects($this->once())
			->method('addResultSet')
			->with($this->anything(), $expected, $expectedExact);

		$attendees = [];
		foreach ($guests as $actorId => $displayName) {
			$attendees[] = Attendee::fromRow([
				'actorId' => $actorId,
				'displayName' => $displayName,
			]);
		}

		$plugin = $this->getPlugin(['createGuestResult']);
		$plugin->expects($this->any())
			->method('createGuestResult')
			->willReturnCallback(function ($hash, $name) {
				return [$hash => $name];
			});

		self::invokePrivate($plugin, 'searchGuests', [$search, $attendees, $result]);
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

	public static function dataCreateResult() {
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
				->method('getDisplayName')
				->with($uid)
				->willReturn($managerName);
		} else {
			$this->userManager->expects($this->any())
				->method('getDisplayName')
				->with($uid)
				->willReturn(null);
		}

		$plugin = $this->getPlugin();
		$this->assertEquals($expected, self::invokePrivate($plugin, 'createResult', [$type, $uid, $name]));
	}


	public static function dataCreateGuestResult(): array {
		return [
			['1234', 'foo', ['label' => 'foo', 'value' => ['shareType' => 'guest', 'shareWith' => 'guest/1234']]],
			['abcd', 'bar', ['label' => 'bar', 'value' => ['shareType' => 'guest', 'shareWith' => 'guest/abcd']]],
		];
	}

	/**
	 * @dataProvider dataCreateGuestResult
	 * @param string $actorId
	 * @param string $name
	 * @param array $expected
	 */
	public function testCreateGuestResult(string $actorId, string $name, array $expected): void {
		$plugin = $this->getPlugin();
		$this->assertEquals($expected, self::invokePrivate($plugin, 'createGuestResult', [$actorId, $name]));
	}

	/**
	 * @dataProvider dataSearchGroups
	 */
	public function testSearchGroups($search, $groupIds, $isGroup, $displayName, $totalMatches, $totalExactMatches): void {
		$this->groupManager
			->method('get')
			->willReturnCallback(function ($groupId) use ($isGroup, $displayName) {
				if ($isGroup) {
					$group = $this->createMock(IGroup::class);
					$group
						->method('getDisplayName')
						->willReturn($displayName);
					$group
						->method('getGID')
						->willReturn($groupId);
					return $group;
				}
			});
		$plugin = $this->getPlugin(['createGroupResult']);
		$plugin->expects($this->any())
			->method('createGroupResult')
			->willReturnCallback(function ($groupId) {
				return [
					'label' => $groupId,
					'value' => [
						'shareType' => 'group',
						'shareWith' => 'group/' . $groupId,
					],
				];
			});
		$searchResult = new SearchResult();
		self::invokePrivate($plugin, 'searchGroups', [$search, $groupIds, $searchResult]);
		$actual = $searchResult->asArray();
		$this->assertCount($totalMatches, $actual['groups']);
		$this->assertCount($totalExactMatches, $actual['exact']['groups']);
	}

	public static function dataSearchGroups(): array {
		return [
			// $search, $groupIds, $isGroup, $displayName, $totalMatches, $totalExactMatches
			['',        ['groupid'], true,  'group', 1, 0],
			['groupid', ['groupid'], true,  'group', 0, 1],
			['gro',     ['groupid'], true,  'group', 1, 0],
			['not',     ['groupid'], false, 'group', 0, 0],
			['name',    ['groupid'], true,  'name',  0, 1],
			['na',      ['groupid'], true,  'name',  1, 0],
			['not',     ['groupid'], true,  'group', 0, 0],
		];
	}
}

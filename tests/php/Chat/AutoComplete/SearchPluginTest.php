<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Chat\AutoComplete;

use OC\Collaboration\Collaborators\SearchResult;
use OCA\Talk\Chat\AutoComplete\SearchPlugin;
use OCA\Talk\Federation\Authenticator;
use OCA\Talk\Files\Util;
use OCA\Talk\GuestManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\TalkSession;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class SearchPluginTest extends TestCase {
	protected IUserManager&MockObject $userManager;
	protected GuestManager&MockObject $guestManager;
	protected TalkSession&MockObject $talkSession;
	protected ParticipantService&MockObject $participantService;
	protected Util&MockObject $util;
	protected Authenticator&MockObject $federationAuthenticator;
	protected IL10N&MockObject $l;
	protected ?string $userId = null;
	protected SearchPlugin $plugin;

	public function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->guestManager = $this->createMock(GuestManager::class);
		$this->talkSession = $this->createMock(TalkSession::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->util = $this->createMock(Util::class);
		$this->federationAuthenticator = $this->createMock(Authenticator::class);
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
				$this->guestManager,
				$this->talkSession,
				$this->participantService,
				$this->util,
				$this->userId,
				$this->l,
				$this->federationAuthenticator,
			);
		}

		return $this->getMockBuilder(SearchPlugin::class)
			->setConstructorArgs([
				$this->userManager,
				$this->guestManager,
				$this->talkSession,
				$this->participantService,
				$this->util,
				$this->userId,
				$this->l,
				$this->federationAuthenticator,
			])
			->onlyMethods($methods)
			->getMock();
	}

	protected function createParticipantMock(string $uid, string $displayName, string $session = ''): Participant {
		/** @var Participant&MockObject $p */
		$p = $this->createMock(Participant::class);
		$a = Attendee::fromRow([
			'actor_type' => $uid ? 'users' : 'guests',
			'actor_id' => $uid ?: sha1($session),
			'display_name' => $displayName,
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

	public function testSearch(): void {
		$result = $this->createMock(ISearchResult::class);
		$room = $this->createMock(Room::class);

		$this->participantService->expects($this->once())
			->method('getParticipantsForRoom')
			->with($room)
			->willReturn([
				$this->createParticipantMock('123', 'OneTwoThree'),
				$this->createParticipantMock('foo', 'Foo Bar'),
				$this->createParticipantMock('', 'Guest 1-6', '123456'),
				$this->createParticipantMock('bar', 'Bar Tender'),
				$this->createParticipantMock('', 'Guest a-f', 'abcdef'),
			]);

		$plugin = $this->getPlugin(['searchUsers', 'searchGuests']);
		$plugin->setContext(['room' => $room]);
		$plugin->expects($this->once())
			->method('searchUsers')
			->with('fo', ['123' => 'OneTwoThree', 'foo' => 'Foo Bar', 'bar' => 'Bar Tender'], $result)
			->willReturnCallback(function ($search, $users, $result): void {
				array_map(function ($user): void {
					$this->assertIsString($user);
				}, $users);
			});
		$plugin->expects($this->once())
			->method('searchGuests')
			->with('fo', $this->anything(), $result)
			->willReturnCallback(function ($search, $guests, $result): void {
				array_map(function ($guest): void {
					$this->assertInstanceOf(Attendee::class, $guest);
				}, $guests);
			});

		$plugin->search('fo', 10, 0, $result);
	}

	public static function dataSearchUsers(): array {
		return [
			['test', [], [], [], []],
			['test', [
				'current' => 'test',
				'foo' => '',
				'test' => 'Te st',
				'test1' => 'Te st 1',
			], [['test1' => 'Te st 1']], [['test' => 'Te st']]],
			['test', [
				'foo' => 'Test',
				'bar' => 'test One',
			], [['bar' => 'test One']], [['foo' => 'Test']]],
			['', ['foo' => '', 'bar' => ''], [['foo' => ''], ['bar' => '']], []],
		];
	}

	/**
	 * @dataProvider dataSearchUsers
	 * @param array<string, string> $users
	 */
	public function testSearchUsers(string $search, array $users, array $expected, array $expectedExact): void {
		$result = $this->createMock(ISearchResult::class);


		$result->expects($this->once())
			->method('addResultSet')
			->with($this->anything(), $expected, $expectedExact);

		$plugin = $this->getPlugin(['createResult']);
		$plugin->method('createResult')
			->willReturnCallback(function ($type, $uid, $name) {
				return [$uid => $name];
			});

		self::invokePrivate($plugin, 'searchUsers', [$search, $users, $result]);
	}

	public static function dataSearchGuests(): array {
		return [
			['test', [], [], []],
			['', ['abcdef' => ''], [['abcdef' => 'Guest']], []],
			['Guest', ['abcdef' => ''], [], [['abcdef' => 'Guest']]],
			['est', ['abcdef' => '', 'foobar' => 'est'], [['abcdef' => 'Guest']], [['foobar' => 'est']]],
			['Ast', ['abcdef' => '', 'foobar' => 'ast'], [], [['foobar' => 'ast']]],
		];
	}

	#[DataProvider('dataSearchGuests')]
	public function testSearchGuests(string $search, array $guests, array $expected, array $expectedExact): void {
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

	public static function dataCreateResult(): array {
		return [
			['user', 'foo', 'bar', '', ['label' => 'bar', 'value' => ['shareType' => 'user', 'shareWith' => 'foo']]],
			['user', 'test', 'Test', '', ['label' => 'Test', 'value' => ['shareType' => 'user', 'shareWith' => 'test']]],
			['user', 'test', '', 'Test', ['label' => 'Test', 'value' => ['shareType' => 'user', 'shareWith' => 'test']]],
			['user', 'test', '', null, ['label' => 'test', 'value' => ['shareType' => 'user', 'shareWith' => 'test']]],
		];
	}

	#[DataProvider('dataCreateResult')]
	public function testCreateResult(string $type, string $uid, string $name, ?string $managerName, array $expected): void {
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

	#[DataProvider('dataCreateGuestResult')]
	public function testCreateGuestResult(string $actorId, string $name, array $expected): void {
		$plugin = $this->getPlugin();
		$this->assertEquals($expected, self::invokePrivate($plugin, 'createGuestResult', [$actorId, $name]));
	}

	public static function dataSearchGroups(): array {
		return [
			// $search, $groups, $isGroup, $totalMatches, $totalExactMatches
			['',        ['groupid' => 'group'], true,  1, 0],
			['groupid', ['groupid' => 'group'], true,  0, 1],
			['gro',     ['groupid' => 'group'], true,  1, 0],
			['not',     ['groupid' => 'group'], false, 0, 0],
			['name',    ['groupid' => 'name'], true,   0, 1],
			['na',      ['groupid' => 'name'], true,   1, 0],
			['not',     ['groupid' => 'group'], true,  0, 0],
		];
	}

	#[DataProvider('dataSearchGroups')]
	public function testSearchGroups(string $search, array $groups, bool $isGroup, int $totalMatches, int $totalExactMatches): void {
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
		self::invokePrivate($plugin, 'searchGroups', [$search, $groups, $searchResult]);
		$actual = $searchResult->asArray();
		$this->assertCount($totalMatches, $actual['groups']);
		$this->assertCount($totalExactMatches, $actual['exact']['groups']);
	}
}

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

namespace OCA\Spreed\Tests\php\Chat\Parser;

use OCA\Spreed\Chat\Parser\SystemMessage;
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\GuestManager;
use OCA\Spreed\Share\RoomShareProvider;
use OCP\Comments\IComment;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class SystemMessageTest extends TestCase {

	/** @var IUserManager|MockObject */
	protected $userManager;
	/** @var GuestManager|MockObject */
	protected $guestManager;
	/** @var IUserSession|MockObject */
	protected $userSession;
	/** @var RoomShareProvider|MockObject */
	protected $shareProvider;
	/** @var IRootFolder|MockObject */
	protected $rootFolder;
	/** @var IURLGenerator|MockObject */
	protected $url;
	/** @var IL10N|MockObject */
	protected $l;

	/** @var SystemMessage */
	protected $parser;

	public function setUp() {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->guestManager = $this->createMock(GuestManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->shareProvider = $this->createMock(RoomShareProvider::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->l = $this->createMock(IL10N::class);
		$this->l->expects($this->any())
			->method('t')
			->will($this->returnCallback(function($text, $parameters = []) {
				return vsprintf($text, $parameters);
			}));
	}

	/**
	 * @param array $methods
	 * @return MockObject|SystemMessage
	 */
	protected function getParser(array $methods = []): SystemMessage {
		if (!empty($methods)) {
			return $this->getMockBuilder(SystemMessage::class)
				->setConstructorArgs([
					$this->userManager,
					$this->guestManager,
					$this->userSession,
					$this->shareProvider,
					$this->rootFolder,
					$this->url,
					$this->l,
				])
				->setMethods($methods)
				->getMock();
		}
		return new SystemMessage(
			$this->userManager,
			$this->guestManager,
			$this->userSession,
			$this->shareProvider,
			$this->rootFolder,
			$this->url,
			$this->l
		);
	}

	public function testSetUserInfoGuestToUser() {
		/** @var IUser $user */
		$user = $this->createMock(IUser::class);
		/** @var IL10N $l */
		$l = $this->createMock(IL10N::class);

		$parser = $this->getParser();
		$this->assertNull(self::invokePrivate($parser, 'recipient'));
		$this->assertSame($this->l, self::invokePrivate($parser, 'l'));
		$parser->setUserInfo($l, $user);
		$this->assertSame($user, self::invokePrivate($parser, 'recipient'));
		$this->assertSame($l, self::invokePrivate($parser, 'l'));
	}

	public function testSetUserInfoUser1ToUser2() {
		/** @var IUser $user1 */
		$user1 = $this->createMock(IUser::class);
		/** @var IUser $user2 */
		$user2 = $this->createMock(IUser::class);
		/** @var IL10N $l */
		$l = $this->createMock(IL10N::class);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user1);

		$parser = $this->getParser();
		$this->assertSame($user1, self::invokePrivate($parser, 'recipient'));
		$this->assertSame($this->l, self::invokePrivate($parser, 'l'));
		$parser->setUserInfo($l, $user2);
		$this->assertSame($user2, self::invokePrivate($parser, 'recipient'));
		$this->assertSame($l, self::invokePrivate($parser, 'l'));
	}

	public function testSetUserInfoUserToGuest() {
		/** @var IUser $user1 */
		$user = $this->createMock(IUser::class);
		/** @var IL10N $l */
		$l = $this->createMock(IL10N::class);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$parser = $this->getParser();
		$this->assertSame($user, self::invokePrivate($parser, 'recipient'));
		$this->assertSame($this->l, self::invokePrivate($parser, 'l'));
		$parser->setUserInfo($l, null);
		$this->assertNull(self::invokePrivate($parser, 'recipient'));
		$this->assertSame($l, self::invokePrivate($parser, 'l'));
	}

	public function dataGetActor(): array {
		return [
			['users', [], ['user'], ['user']],
			['guests', ['guest'], [], ['guest']],
		];
	}

	/**
	 * @dataProvider dataGetActor
	 * @param string $actorType
	 * @param array $guestData
	 * @param array $userData
	 * @param array $expected
	 */
	public function testGetActor(string $actorType, array $guestData, array $userData, array $expected) {
		$chatMessage = $this->createMock(IComment::class);
		$chatMessage->expects($this->once())
			->method('getActorType')
			->willReturn($actorType);
		$chatMessage->expects($this->once())
			->method('getActorId')
			->willReturn('author-id');

		$parser = $this->getParser(['getGuest', 'getUser']);
		if (empty($guestData)) {
			$parser->expects($this->never())
				->method('getGuest');
		} else {
			$parser->expects($this->once())
				->method('getGuest')
				->with('author-id')
				->willReturn($guestData);
		}

		if (empty($userData)) {
			$parser->expects($this->never())
				->method('getUser');
		} else {
			$parser->expects($this->once())
				->method('getUser')
				->with('author-id')
				->willReturn($userData);
		}

		$this->assertSame($expected, self::invokePrivate($parser, 'getActor', [$chatMessage]));
	}

	public function dataGetUser(): array {
		return [
			['test', [], false, 'Test'],
			['foo', ['admin' => 'Admin'], false, 'Bar'],
			['admin', ['admin' => 'Administrator'], true, 'Administrator'],
		];
	}

	/**
	 * @dataProvider dataGetUser
	 * @param string $uid
	 * @param array $cache
	 * @param bool $cacheHit
	 * @param string $name
	 */
	public function testGetUser(string $uid, array $cache, bool $cacheHit, string $name) {
		$parser = $this->getParser(['getDisplayName']);

		self::invokePrivate($parser, 'displayNames', [$cache]);

		if (!$cacheHit) {
			$parser->expects($this->once())
				->method('getDisplayName')
				->with($uid)
				->willReturn($name);
		} else {
			$parser->expects($this->never())
				->method('getDisplayName');
		}

		$result = self::invokePrivate($parser, 'getUser', [$uid]);
		$this->assertSame('user', $result['type']);
		$this->assertSame($uid, $result['id']);
		$this->assertSame($name, $result['name']);
	}

	public function dataGetDisplayName(): array {
		return [
			['test', true, 'Test'],
			['foo', false, 'foo'],
		];
	}

	/**
	 * @dataProvider dataGetDisplayName
	 * @param string $uid
	 * @param bool $validUser
	 * @param string $name
	 */
	public function testGetDisplayName(string $uid, bool $validUser, string $name) {
		$parser = $this->getParser();

		if ($validUser) {
			$user = $this->createMock(IUser::class);
			$user->expects($this->once())
				->method('getDisplayName')
				->willReturn($name);
			$this->userManager->expects($this->once())
				->method('get')
				->with($uid)
				->willReturn($user);
		} else {
			$this->userManager->expects($this->once())
				->method('get')
				->with($uid)
				->willReturn(null);
		}

		$this->assertSame($name, self::invokePrivate($parser, 'getDisplayName', [$uid]));
	}

	public function testGetGuest() {
		$sessionHash = sha1('name');
		$parser = $this->getParser(['getGuestName']);
		$parser->expects($this->once())
			->method('getGuestName')
			->with($sessionHash)
			->willReturn('name');

		$this->assertSame([
			'type' => 'guest',
			'id' => $sessionHash,
			'name' => 'name',
		], self::invokePrivate($parser, 'getGuest', [$sessionHash]));

		// Cached call: no call to getGuestName() again
		$this->assertSame([
			'type' => 'guest',
			'id' => $sessionHash,
			'name' => 'name',
		], self::invokePrivate($parser, 'getGuest', [$sessionHash]));
	}

	public function testGetGuestName() {
		$sessionHash = sha1('name');
		$this->guestManager->expects($this->once())
			->method('getNameBySessionHash')
			->with($sessionHash)
			->willReturn('name');

		$parser = $this->getParser();
		$this->assertSame('name (guest)', self::invokePrivate($parser, 'getGuestName', [$sessionHash]));
	}

	public function testGetGuestNameThrows() {
		$sessionHash = sha1('name');
		$this->guestManager->expects($this->once())
			->method('getNameBySessionHash')
			->with($sessionHash)
			->willThrowException(new ParticipantNotFoundException());

		$parser = $this->getParser();
		$this->assertSame('Guest', self::invokePrivate($parser, 'getGuestName', [$sessionHash]));
	}

	public function dataGetDuration(): array {
		return [
			[30, '0:30'],
			[140, '2:20'],
			[5421, '1:30:21'],
			[7221, '2:00:21'],
		];
	}

	/**
	 * @dataProvider dataGetDuration
	 * @param int $seconds
	 * @param string $expected
	 */
	public function testGetDuration(int $seconds, string $expected) {
		$parser = $this->getParser();
		$this->assertSame($expected, self::invokePrivate($parser, 'getDuration', [$seconds]));
	}
}

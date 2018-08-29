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
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;
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
		$this->l->expects($this->any())
			->method('n')
			->will($this->returnCallback(function($singular, $plural, $count, $parameters = []) {
				$text = $count === 1 ? $singular : $plural;
				return vsprintf(str_replace('%n', $count, $text), $parameters);
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

	public function testGetFileFromShareForGuest() {
		$node = $this->createMock(Node::class);
		$node->expects($this->once())
			->method('getId')
			->willReturn('54');
		$node->expects($this->once())
			->method('getName')
			->willReturn('name');

		$share = $this->createMock(IShare::class);
		$share->expects($this->once())
			->method('getNode')
			->willReturn($node);
		$share->expects($this->once())
			->method('getToken')
			->willReturn('token');

		$this->shareProvider->expects($this->once())
			->method('getShareById')
			->with('23')
			->willReturn($share);

		$this->url->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('files_sharing.sharecontroller.showShare', [
				'token' => 'token',
			])
			->willReturn('absolute-link');

		$parser = $this->getParser();
		$this->assertSame([
			'type' => 'file',
			'id' => '54',
			'name' => 'name',
			'path' => 'name',
			'link' => 'absolute-link',
		], self::invokePrivate($parser, 'getFileFromShare', ['23']));
	}

	public function testGetFileFromShareForOwner() {
		$node = $this->createMock(Node::class);
		$node->expects($this->exactly(2))
			->method('getId')
			->willReturn('54');
		$node->expects($this->once())
			->method('getName')
			->willReturn('name');
		$node->expects($this->once())
			->method('getPath')
			->willReturn('/owner/files/path/to/file/name');

		$share = $this->createMock(IShare::class);
		$share->expects($this->once())
			->method('getNode')
			->willReturn($node);

		$this->shareProvider->expects($this->once())
			->method('getShareById')
			->with('23')
			->willReturn($share);

		$this->url->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('files.viewcontroller.showFile', [
				'fileid' => '54',
			])
			->willReturn('absolute-link-owner');

		$this->userSession->expects($this->exactly(2))
			->method('getUser')
			->willReturn($this->createMock(IUser::class));

		$parser = $this->getParser();
		$this->assertSame([
			'type' => 'file',
			'id' => '54',
			'name' => 'name',
			'path' => 'path/to/file/name',
			'link' => 'absolute-link-owner',
		], self::invokePrivate($parser, 'getFileFromShare', ['23']));
	}

	public function testGetFileFromShareForRecipient() {
		$node = $this->createMock(Node::class);
		$node->expects($this->exactly(3))
			->method('getId')
			->willReturn('54');
		$node->expects($this->once())
			->method('getName')
			->willReturn('name');

		$share = $this->createMock(IShare::class);
		$share->expects($this->once())
			->method('getNode')
			->willReturn($node);

		$recipient = $this->createMock(IUser::class);
		$recipient->expects($this->once())
			->method('getUID')
			->willReturn('user');

		$this->shareProvider->expects($this->once())
			->method('getShareById')
			->with('23')
			->willReturn($share);

		$file = $this->createMock(Node::class);
		$file->expects($this->once())
			->method('getName')
			->willReturn('different');
		$file->expects($this->once())
			->method('getPath')
			->willReturn('/user/files/Shared/different');

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('getById')
			->with('54')
			->willReturn([$file]);

		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user')
			->willReturn($userFolder);

		$this->url->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('files.viewcontroller.showFile', [
				'fileid' => '54',
			])
			->willReturn('absolute-link-owner');

		$this->userSession->expects($this->exactly(2))
			->method('getUser')
			->willReturnOnConsecutiveCalls(
				$recipient,
				$this->createMock(IUser::class)
			);

		$parser = $this->getParser();
		$this->assertSame([
			'type' => 'file',
			'id' => '54',
			'name' => 'different',
			'path' => 'Shared/different',
			'link' => 'absolute-link-owner',
		], self::invokePrivate($parser, 'getFileFromShare', ['23']));
	}

	/**
	 * @expectedException \OCP\Files\NotFoundException
	 */
	public function testGetFileFromShareForRecipientThrows() {
		$node = $this->createMock(Node::class);
		$node->expects($this->once())
			->method('getId')
			->willReturn('54');
		$node->expects($this->once())
			->method('getName')
			->willReturn('name');

		$share = $this->createMock(IShare::class);
		$share->expects($this->once())
			->method('getNode')
			->willReturn($node);

		$recipient = $this->createMock(IUser::class);
		$recipient->expects($this->once())
			->method('getUID')
			->willReturn('user');

		$this->shareProvider->expects($this->once())
			->method('getShareById')
			->with('23')
			->willReturn($share);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('getById')
			->with('54')
			->willReturn([]);

		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user')
			->willReturn($userFolder);

		$this->url->expects($this->never())
			->method('linkToRouteAbsolute');

		$this->userSession->expects($this->exactly(2))
			->method('getUser')
			->willReturnOnConsecutiveCalls(
				$recipient,
				$this->createMock(IUser::class)
			);

		$parser = $this->getParser();
		self::invokePrivate($parser, 'getFileFromShare', ['23']);
	}

	/**
	 * @expectedException \OCP\Share\Exceptions\ShareNotFound
	 */
	public function testGetFileFromShareThrows() {

		$this->shareProvider->expects($this->once())
			->method('getShareById')
			->with('23')
			->willThrowException(new ShareNotFound());

		$parser = $this->getParser();
		self::invokePrivate($parser, 'getFileFromShare', ['23']);
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

	public function dataParseCall(): array {
		return [
			'1 user + guests' => [
				['users' => ['user1'], 'guests' => 3, 'duration' => 42],
				[
					'Call with {user1} and 3 guests (Duration "duration")',
					['user1' => ['data' => 'user1']],
				],
			],
			'2 users' => [
				['users' => ['user1', 'user2'], 'guests' => 0, 'duration' => 42],
				[
					'Call with {user1} and {user2} (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2']],
				],
			],
			'2 users + guests' => [
				['users' => ['user1', 'user2'], 'guests' => 1, 'duration' => 42],
				[
					'Call with {user1}, {user2} and 1 guest (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2']],
				],
			],
			'3 users' => [
				['users' => ['user1', 'user2', 'user3'], 'guests' => 0, 'duration' => 42],
				[
					'Call with {user1}, {user2} and {user3} (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3']],
				],
			],
			'3 users + guests' => [
				['users' => ['user1', 'user2', 'user3'], 'guests' => 22, 'duration' => 42],
				[
					'Call with {user1}, {user2}, {user3} and 22 guests (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3']],
				],
			],
			'4 users' => [
				['users' => ['user1', 'user2', 'user3', 'user4'], 'guests' => 0, 'duration' => 42],
				[
					'Call with {user1}, {user2}, {user3} and {user4} (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3'], 'user4' => ['data' => 'user4']],
				],
			],
			'4 users + guests' => [
				['users' => ['user1', 'user2', 'user3', 'user4'], 'guests' => 4, 'duration' => 42],
				[
					'Call with {user1}, {user2}, {user3}, {user4} and 4 guests (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3'], 'user4' => ['data' => 'user4']],
				],
			],
			'5 users' => [
				['users' => ['user1', 'user2', 'user3', 'user4', 'user5'], 'guests' => 0, 'duration' => 42],
				[
					'Call with {user1}, {user2}, {user3}, {user4} and {user5} (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3'], 'user4' => ['data' => 'user4'], 'user5' => ['data' => 'user5']],
				],
			],
			'5 users + guests' => [
				['users' => ['user1', 'user2', 'user3', 'user4', 'user5'], 'guests' => 1, 'duration' => 42],
				[
					'Call with {user1}, {user2}, {user3}, {user4} and 2 others (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3'], 'user4' => ['data' => 'user4']],
				],
			],
			'6 users' => [
				['users' => ['user1', 'user2', 'user3', 'user4', 'user5', 'user6'], 'guests' => 0, 'duration' => 42],
				[
					'Call with {user1}, {user2}, {user3}, {user4} and 2 others (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3'], 'user4' => ['data' => 'user4']],
				],
			],
			'6 users + guests' => [
				['users' => ['user1', 'user2', 'user3', 'user4', 'user5', 'user6'], 'guests' => 2, 'duration' => 42],
				[
					'Call with {user1}, {user2}, {user3}, {user4} and 4 others (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3'], 'user4' => ['data' => 'user4']],
				],
			],
		];
	}

	/**
	 * @dataProvider dataParseCall
	 * @param array $parameters
	 * @param array $expected
	 */
	public function testParseCall(array $parameters, array $expected) {
		$parser = $this->getParser(['getDuration', 'getUser']);
		$parser->expects($this->once())
			->method('getDuration')
			->with(42)
			->willReturn('"duration"');

		$parser->expects($this->any())
			->method('getUser')
			->willReturnCallback(function($user) {
				return ['data' => $user];
			});

		$this->assertSame($expected, self::invokePrivate($parser, 'parseCall', [$parameters]));
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

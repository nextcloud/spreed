<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Tests\php\Activity;


use OCA\Spreed\Activity\Provider;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use Test\TestCase;

/**
 * Class ProviderTest
 *
 * @package OCA\Spreed\Tests\php\Activity
 */
class ProviderTest extends TestCase {

	/** @var IFactory|\PHPUnit_Framework_MockObject_MockObject */
	protected $l10nFactory;
	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	protected $url;
	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $activityManager;
	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;
	/** @var Manager|\PHPUnit_Framework_MockObject_MockObject */
	protected $manager;

	public function setUp() {
		parent::setUp();

		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->activityManager = $this->createMock(IManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->manager = $this->createMock(Manager::class);
	}

	/**
	 * @param string[] $methods
	 * @return Provider|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getProvider(array $methods = []) {
		if (!empty($methods)) {
			return $this->getMockBuilder(Provider::class)
				->setConstructorArgs([
					$this->l10nFactory,
					$this->url,
					$this->activityManager,
					$this->userManager,
					$this->manager,
				])
				->setMethods($methods)
				->getMock();
		}
		return new Provider(
			$this->l10nFactory,
			$this->url,
			$this->activityManager,
			$this->userManager,
			$this->manager
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testParseThrows() {
		/** @var IEvent|\PHPUnit_Framework_MockObject_MockObject $event */
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('getApp')
			->willReturn('activity');
		$provider = $this->getProvider();
		$provider->parse('en', $event, null);
	}

	public function dataSetSubject() {
		return [
			['No placeholder', [], 'No placeholder'],
			['This has one {placeholder}', ['placeholder' => ['name' => 'foobar']], 'This has one foobar'],
			['This has {number} {placeholders}', ['number' => ['name' => 'two'], 'placeholders' => ['name' => 'foobars']], 'This has two foobars'],
		];
	}

	/**
	 * @dataProvider dataSetSubject
	 *
	 * @param string $subject
	 * @param array $parameters
	 * @param string $parsedSubject
	 */
	public function testSetSubject($subject, array $parameters, $parsedSubject) {
		$provider = $this->getProvider();

		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('setParsedSubject')
			->with($parsedSubject)
			->willReturnSelf();
		$event->expects($this->once())
			->method('setRichSubject')
			->with($subject, $parameters)
			->willReturnSelf();

		self::invokePrivate($provider, 'setSubjects', [$event, $subject, $parameters]);
	}

	public function dataGetParameters() {
		return [
			['test', true, ['actor' => 'array(user)', 'call' => 'array(room)']],
			['admin', false, ['actor' => 'array(user)']],
		];
	}

	/**
	 * @dataProvider dataGetParameters
	 *
	 * @param string $user
	 * @param bool $isRoom
	 * @param array $expected
	 */
	public function testGetParameters($user, $isRoom, array $expected) {
		$provider = $this->getProvider(['getUser', 'getRoom']);

		$provider->expects($this->once())
			->method('getUser')
			->with($user)
			->willReturn('array(user)');

		if ($isRoom) {
			$room = $this->createMock(Room::class);
			$provider->expects($this->once())
				->method('getRoom')
				->with($room)
				->willReturn('array(room)');
		} else {
			$room = null;
			$provider->expects($this->never())
				->method('getRoom');
		}

		$this->assertEquals($expected, self::invokePrivate($provider, 'getParameters', [['user' => $user], $room]));
	}

	public function dataGetRoom() {
		return [
			[Room::ONE_TO_ONE_CALL, 23, 'private-call', 'one2one'],
			[Room::GROUP_CALL, 42, 'group-call', 'group'],
			[Room::PUBLIC_CALL, 128, 'public-call', 'public'],
		];
	}

	/**
	 * @dataProvider dataGetRoom
	 *
	 * @param int $type
	 * @param int $id
	 * @param string $name
	 * @param string $expectedType
	 */
	public function testGetRoom($type, $id, $name, $expectedType) {
		$provider = $this->getProvider();

		$room = $this->createMock(Room::class);
		$room->expects($this->once())
			->method('getType')
			->willReturn($type);
		$room->expects($this->once())
			->method('getId')
			->willReturn($id);
		$room->expects($this->once())
			->method('getName')
			->willReturn($name);

		$this->assertEquals([
			'type' => 'call',
			'id' => $id,
			'name' => $name,
			'call-type' => $expectedType,
		], self::invokePrivate($provider, 'getRoom', [$room]));
	}

	public function dataGetUser() {
		return [
			['test', [], false, 'Test'],
			['foo', ['admin' => 'Admin'], false, 'Bar'],
			['admin', ['admin' => 'Administrator'], true, 'Administrator'],
		];
	}

	/**
	 * @dataProvider dataGetUser
	 *
	 * @param string $uid
	 * @param array $cache
	 * @param bool $cacheHit
	 * @param string $name
	 */
	public function testGetUser($uid, $cache, $cacheHit, $name) {
		$provider = $this->getProvider(['getDisplayName']);

		self::invokePrivate($provider, 'displayNames', [$cache]);

		if (!$cacheHit) {
			$provider->expects($this->once())
				->method('getDisplayName')
				->with($uid)
				->willReturn($name);
		} else {
			$provider->expects($this->never())
				->method('getDisplayName');
		}

		$result = self::invokePrivate($provider, 'getUser', [$uid]);
		$this->assertSame('user', $result['type']);
		$this->assertSame($uid, $result['id']);
		$this->assertSame($name, $result['name']);
	}

	public function dataGetDisplayName() {
		return [
			['test', true, 'Test'],
			['foo', false, 'foo'],
		];
	}

	/**
	 * @dataProvider dataGetDisplayName
	 *
	 * @param string $uid
	 * @param bool $validUser
	 * @param string $name
	 */
	public function testGetDisplayName($uid, $validUser, $name) {
		$provider = $this->getProvider();

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

		$this->assertSame($name, self::invokePrivate($provider, 'getDisplayName', [$uid]));
	}
}

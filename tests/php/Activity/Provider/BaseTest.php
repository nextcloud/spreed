<?php

declare(strict_types=1);
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

namespace OCA\Talk\Tests\php\Activity\Provider;

use OCA\Talk\Activity\Provider\Base;
use OCA\Talk\Config;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * Class BaseTest
 *
 * @package OCA\Talk\Tests\php\Activity
 */
class BaseTest extends TestCase {
	protected IFactory&MockObject $l10nFactory;
	protected IURLGenerator&MockObject $url;
	protected Config&MockObject $config;
	protected IManager&MockObject $activityManager;
	protected IUserManager&MockObject $userManager;
	protected AvatarService&MockObject $avatarService;
	protected Manager&MockObject $manager;

	public function setUp(): void {
		parent::setUp();

		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->config = $this->createMock(Config::class);
		$this->activityManager = $this->createMock(IManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->avatarService = $this->createMock(AvatarService::class);
		$this->manager = $this->createMock(Manager::class);
	}

	/**
	 * @param string[] $methods
	 */
	protected function getProvider(array $methods = []): Base&MockObject {
		$methods[] = 'parse';
		return $this->getMockBuilder(Base::class)
			->setConstructorArgs([
				$this->l10nFactory,
				$this->url,
				$this->config,
				$this->activityManager,
				$this->userManager,
				$this->avatarService,
				$this->manager,
			])
			->onlyMethods($methods)
			->getMock();
	}


	public static function dataPreParse(): array {
		return [
			['other',  false,  true,  true],
			['spreed', false,  true,  true],
			['spreed', true, true,  true],
			['spreed', true, false, false],
		];
	}

	/**
	 * @dataProvider dataPreParse
	 */
	public function testPreParse(string $appId, bool $hasUser, bool $disabledForUser, bool $willThrowException): void {
		$user = $hasUser ? $this->createMock(IUser::class) : null;

		/** @var IEvent&MockObject $event */
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('getApp')
			->willReturn($appId);

		if ($willThrowException) {
			$this->expectException(UnknownActivityException::class);
		}
		$event->expects($this->exactly($willThrowException ? 0 : 1))
			->method('setIcon')
			->willReturnSelf();

		if ($user) {
			$this->config
				->method('isDisabledForUser')
				->with($user)
				->willReturn($disabledForUser);
			$this->userManager
				->method('get')
				->with('user')
				->willReturn($user);
			$event->expects($this->once())
				->method('getAffectedUser')
				->willReturn('user');
		}

		$provider = $this->getProvider();
		static::invokePrivate($provider, 'preParse', [$event]);
	}

	public function testPreParseThrows(): void {
		/** @var IEvent&MockObject $event */
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('getApp')
			->willReturn('activity');
		$provider = $this->getProvider();
		$this->expectException(UnknownActivityException::class);
		static::invokePrivate($provider, 'preParse', [$event]);
	}

	public static function dataSetSubject(): array {
		return [
			['No placeholder', [], 'No placeholder'],
			['This has one {placeholder}', ['placeholder' => ['name' => 'foobar']], 'This has one foobar'],
			['This has {number} {placeholders}', ['number' => ['name' => 'two'], 'placeholders' => ['name' => 'foobars']], 'This has two foobars'],
		];
	}

	/**
	 * @dataProvider dataSetSubject
	 */
	public function testSetSubject(string $subject, array $parameters, string $parsedSubject): void {
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

	public static function dataGetRoom(): array {
		return [
			[Room::TYPE_ONE_TO_ONE, 23, 'private-call', 'private-call', 'one2one'],
			[Room::TYPE_GROUP, 42, 'group-call', 'group-call', 'group'],
			[Room::TYPE_PUBLIC, 128, 'public-call', 'public-call', 'public'],
			[Room::TYPE_ONE_TO_ONE, 23, '', 'a conversation', 'one2one'],
			[Room::TYPE_GROUP, 42, '', 'a conversation', 'group'],
			[Room::TYPE_PUBLIC, 128, '', 'a conversation', 'public'],
		];
	}

	/**
	 * @dataProvider dataGetRoom
	 */
	public function testGetRoom(int $type, int $id, string $name, string $expectedName, string $expectedType): void {
		$provider = $this->getProvider();

		$room = $this->createMock(Room::class);
		$room->expects($this->once())
			->method('getType')
			->willReturn($type);
		$room->expects($this->once())
			->method('getId')
			->willReturn($id);
		$room->expects($this->once())
			->method('getDisplayName')
			->with('user')
			->willReturn($expectedName);
		$room->expects($this->once())
			->method('getToken')
			->willReturn('token');

		$this->url->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('spreed.Page.showCall', ['token' => 'token'])
			->willReturn('url');

		$this->assertEquals([
			'type' => 'call',
			'id' => $id,
			'name' => $expectedName,
			'call-type' => $expectedType,
			'link' => 'url',
			'icon-url' => '',
		], self::invokePrivate($provider, 'getRoom', [$room, 'user']));
	}

	public static function dataGetUser(): array {
		return [
			['test', true, 'Test'],
			['foo', false, 'foo'],
		];
	}

	/**
	 * @dataProvider dataGetUser
	 */
	public function testGetUser(string $uid, bool $validUser, string $name): void {
		$provider = $this->getProvider();

		if ($validUser) {
			$this->userManager->expects($this->once())
				->method('getDisplayName')
				->with($uid)
				->willReturn($name);
		} else {
			$this->userManager->expects($this->once())
				->method('getDisplayName')
				->with($uid)
				->willReturn(null);
		}

		$this->assertSame([
			'type' => 'user',
			'id' => $uid,
			'name' => $name,
		], self::invokePrivate($provider, 'getUser', [$uid]));
	}
}

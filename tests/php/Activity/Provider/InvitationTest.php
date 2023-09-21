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

namespace OCA\Talk\Tests\php\Activity\Provider;

use OCA\Talk\Activity\Provider\Invitation;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * Class InvitationTest
 *
 * @package OCA\Talk\Tests\php\Activity
 */
class InvitationTest extends TestCase {
	/** @var IFactory|MockObject */
	protected $l10nFactory;
	/** @var IURLGenerator|MockObject */
	protected $url;
	/** @var Config|MockObject */
	protected $config;
	/** @var IManager|MockObject */
	protected $activityManager;
	/** @var IUserManager|MockObject */
	protected $userManager;
	/** @var AvatarService|MockObject */
	protected $avatarService;
	/** @var Manager|MockObject */
	protected $manager;

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
	 * @return Invitation|MockObject
	 */
	protected function getProvider(array $methods = []) {
		if (!empty($methods)) {
			return $this->getMockBuilder(Invitation::class)
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
		return new Invitation(
			$this->l10nFactory,
			$this->url,
			$this->config,
			$this->activityManager,
			$this->userManager,
			$this->avatarService,
			$this->manager
		);
	}

	public function testParseThrowsWrongSubject() {
		/** @var IEvent|MockObject $event */
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('getApp')
			->willReturn('spreed');
		$event->expects($this->once())
			->method('getSubject')
			->willReturn('call');
		$event->expects($this->once())
			->method('getAffectedUser')
			->willReturn('user');

		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('user')
			->willReturn($user);
		$this->config->expects($this->once())
			->method('isDisabledForUser')
			->with($user)
			->willReturn(false);

		$provider = $this->getProvider();
		$this->expectException(\InvalidArgumentException::class);
		$provider->parse('en', $event);
	}

	public static function dataParse() {
		return [
			['en', true, ['room' => 23, 'user' => 'test1'], ['actor' => ['actor-data'], 'call' => ['call-data']]],
			['de', false, ['room' => 42, 'user' => 'test2'], ['actor' => ['actor-data'], 'call' => ['call-unknown']]],
		];
	}

	/**
	 * @dataProvider dataParse
	 *
	 * @param string $lang
	 * @param bool $roomExists
	 * @param array $params
	 * @param array $expectedParams
	 */
	public function testParse($lang, $roomExists, array $params, array $expectedParams) {
		$provider = $this->getProvider(['setSubjects', 'getUser', 'getRoom', 'getFormerRoom']);

		/** @var IL10N|MockObject $l */
		$l = $this->createMock(IL10N::class);
		$l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});

		/** @var IEvent|MockObject $event */
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('getApp')
			->willReturn('spreed');
		$event->expects($this->once())
			->method('getSubject')
			->willReturn('invitation');
		$event->expects($this->once())
			->method('getSubjectParameters')
			->willReturn($params);
		$event->expects($this->exactly($roomExists ? 2 : 1))
			->method('getAffectedUser')
			->willReturn('user');

		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('user')
			->willReturn($user);
		$this->config->expects($this->once())
			->method('isDisabledForUser')
			->with($user)
			->willReturn(false);

		if ($roomExists) {
			/** @var Room|MockObject $room */
			$room = $this->createMock(Room::class);

			$this->manager->expects($this->once())
				->method('getRoomById')
				->with($params['room'])
				->willReturn($room);

			$provider->expects($this->once())
				->method('getRoom')
				->with($room, 'user')
				->willReturn(['call-data']);
		} else {
			$this->manager->expects($this->once())
				->method('getRoomById')
				->with($params['room'])
				->willThrowException(new RoomNotFoundException());

			$provider->expects($this->never())
				->method('getRoom');
		}

		$this->l10nFactory->expects($this->once())
			->method('get')
			->with('spreed', $lang)
			->willReturn($l);

		$provider->expects($this->once())
			->method('getUser')
			->with($params['user'])
			->willReturn(['actor-data']);
		$provider->expects($this->once())
			->method('setSubjects')
			->with($event, '{actor} invited you to {call}', $expectedParams);
		$provider->expects($this->once())
			->method('getUser')
			->with($params['user'])
			->willReturn(['actor-data']);
		$provider->expects($this->once())
			->method('getFormerRoom')
			->with($l, $params['room'])
			->willReturn(['call-unknown']);

		$provider->parse($lang, $event);
	}
}

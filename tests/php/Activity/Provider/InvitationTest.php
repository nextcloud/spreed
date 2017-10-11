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

namespace OCA\Spreed\Tests\php\Activity\Provider;


use OCA\Spreed\Activity\Provider\Invitation;
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
 * Class InvitationTest
 *
 * @package OCA\Spreed\Tests\php\Activity
 */
class InvitationTest extends TestCase {

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
	 * @return Invitation|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getProvider(array $methods = []) {
		if (!empty($methods)) {
			return $this->getMockBuilder(Invitation::class)
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
		return new Invitation(
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

}

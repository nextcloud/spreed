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
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IL10N;
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

	public function dataParseThrows() {
		return [
			['call', null],
			['invitation', true],
		];
	}

	/**
	 * @dataProvider dataParseThrows
	 *
	 * @param string $subject
	 * @param bool|null $roomNotFound
	 * @expectedException \InvalidArgumentException
	 */
	public function testParseThrows($subject, $roomNotFound) {
		/** @var IEvent|\PHPUnit_Framework_MockObject_MockObject $event */
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('getApp')
			->willReturn('spreed');
		$event->expects($this->once())
			->method('getSubject')
			->willReturn($subject);

		$this->manager->expects($roomNotFound === null ? $this->never() : $this->once())
			->method('getRoomById')
			->willThrowException(new RoomNotFoundException());

		$provider = $this->getProvider();
		$provider->parse('en', $event, null);
	}

	public function dataParse() {
		return [
			['en', ['room' => 23], ['subject' => 'Subject1', 'params' => ['Params1']]],
			['de', ['room' => 42], ['subject' => 'Subject2', 'params' => ['Params2']]],
		];
	}

	/**
	 * @dataProvider dataParse
	 *
	 * @param string $lang
	 * @param array $params
	 * @param array $result
	 */
	public function testParse($lang, array $params, array $result) {
		/** @var IEvent|\PHPUnit_Framework_MockObject_MockObject $event */
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

		/** @var Room|\PHPUnit_Framework_MockObject_MockObject $room */
		$room = $this->createMock(Room::class);

		$this->manager->expects($this->once())
			->method('getRoomById')
			->with($params['room'])
			->willReturn($room);

		/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject $l */
		$l = $this->createMock(IL10N::class);

		$this->l10nFactory->expects($this->once())
			->method('get')
			->with('spreed', $lang)
			->willReturn($l);

		$provider = $this->getProvider(['parseInvitation', 'setSubjects']);
		$provider->expects($this->once())
			->method('parseInvitation')
			->with($event, $l, $room)
			->willReturn($result);
		$provider->expects($this->once())
			->method('setSubjects')
			->with($event, $result['subject'], $result['params']);

		$provider->parse($lang, $event);
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

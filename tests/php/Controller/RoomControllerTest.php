<?php

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Spreed\Tests\php\Controller;

use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Chat\MessageParser;
use OCA\Spreed\Controller\RoomController;
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\GuestManager;
use OCA\Spreed\Manager;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCA\Spreed\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;

class RoomControllerTest extends \Test\TestCase {

	/** @var string */
	private $userId;
	/** @var TalkSession|MockObject */
	private $talkSession;
	/** @var IUserManager|MockObject */
	protected $userManager;
	/** @var IGroupManager|MockObject */
	protected $groupManager;
	/** @var Manager|MockObject */
	protected $manager;
	/** @var ChatManager|MockObject */
	protected $chatManager;
	/** @var GuestManager|MockObject */
	protected $guestManager;
	/** @var MessageParser|MockObject */
	protected $messageParser;
	/** @var ITimeFactory|MockObject */
	protected $timeFactory;
	/** @var IL10N|MockObject */
	private $l;


	public function setUp() {
		parent::setUp();

		$this->userId = 'testUser';
		$this->talkSession = $this->createMock(TalkSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->manager = $this->createMock(Manager::class);
		$this->guestManager = $this->createMock(GuestManager::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->l = $this->createMock(IL10N::class);
	}

	private function getController() {
		return new RoomController(
			'spreed',
			$this->userId,
			$this->createMock(IRequest::class),
			$this->talkSession,
			$this->userManager,
			$this->groupManager,
			$this->manager,
			$this->guestManager,
			$this->chatManager,
			$this->messageParser,
			$this->timeFactory,
			$this->l
		);
	}

	public function dataSetNotificationLevel(): array {
		return [
			['token1', Participant::NOTIFY_ALWAYS, true],
			['token2', Participant::NOTIFY_MENTION, true],
			['token3', Participant::NOTIFY_NEVER, true],
			['token4', Participant::NOTIFY_DEFAULT, false, Http::STATUS_BAD_REQUEST],
		];
	}

	/**
	 * @dataProvider dataSetNotificationLevel
	 * @param string $token
	 * @param int $level
	 * @param bool $validSet
	 * @param int $status
	 */
	public function testSetNotificationLevel(string $token, int $level, bool $validSet, int $status = Http::STATUS_OK) {
		$participant = $this->createMock(Participant::class);
		$participant->expects($this->once())
			->method('setNotificationLevel')
			->with($level)
			->willReturn($validSet);

		$room = $this->createMock(Room::class);
		$room->expects($this->once())
			->method('getParticipant')
			->with($this->userId)
			->willReturn($participant);

		$this->manager->expects($this->once())
			->method('getRoomForParticipantByToken')
			->with($token, $this->userId)
			->willReturn($room);

		$controller = $this->getController();
		$expected = new DataResponse([], $status);

		$this->assertEquals($expected, $controller->setNotificationLevel($token, $level));
	}

	public function testSetNotificationLevelThrowsParticipant() {
		$token = 'token';

		$room = $this->createMock(Room::class);
		$room->expects($this->once())
			->method('getParticipant')
			->with($this->userId)
			->willThrowException(new ParticipantNotFoundException('Participant not found'));

		$this->manager->expects($this->once())
			->method('getRoomForParticipantByToken')
			->with($token, $this->userId)
			->willReturn($room);

		$controller = $this->getController();
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $controller->setNotificationLevel($token, Participant::NOTIFY_ALWAYS));
	}

	public function testSetNotificationLevelThrowsRoom() {
		$token = 'token';

		$this->manager->expects($this->once())
			->method('getRoomForParticipantByToken')
			->with($token, $this->userId)
			->willThrowException(new RoomNotFoundException('Room not found'));

		$controller = $this->getController();
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $controller->setNotificationLevel($token, Participant::NOTIFY_MENTION));
	}
}

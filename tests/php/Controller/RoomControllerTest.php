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

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Controller\RoomController;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\GuestManager;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
	/** @var EventDispatcherInterface|MockObject */
	protected $dispatcher;
	/** @var MessageParser|MockObject */
	protected $messageParser;
	/** @var ITimeFactory|MockObject */
	protected $timeFactory;
	/** @var IL10N|MockObject */
	private $l;


	public function setUp(): void {
		parent::setUp();

		$this->userId = 'testUser';
		$this->talkSession = $this->createMock(TalkSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->manager = $this->createMock(Manager::class);
		$this->guestManager = $this->createMock(GuestManager::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->dispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->l = $this->createMock(IL10N::class);
	}

	/**
	 * @param Room|MockObject $room
	 * @param Participant|MockObject $participant
	 * @return RoomController
	 */
	private function getController(Room $room, Participant $participant): RoomController {
		$controller = new RoomController(
			'spreed',
			$this->userId,
			$this->createMock(IRequest::class),
			$this->talkSession,
			$this->userManager,
			$this->groupManager,
			$this->manager,
			$this->guestManager,
			$this->chatManager,
			$this->dispatcher,
			$this->messageParser,
			$this->timeFactory,
			$this->l
		);
		$controller->setRoom($room);
		$controller->setParticipant($participant);
		return $controller;
	}

	public function dataSetNotificationLevel(): array {
		return [
			[Participant::NOTIFY_ALWAYS, true],
			[Participant::NOTIFY_MENTION, true],
			[Participant::NOTIFY_NEVER, true],
			[Participant::NOTIFY_DEFAULT, false, Http::STATUS_BAD_REQUEST],
		];
	}

	/**
	 * @dataProvider dataSetNotificationLevel
	 * @param int $level
	 * @param bool $validSet
	 * @param int $status
	 */
	public function testSetNotificationLevel(int $level, bool $validSet, int $status = Http::STATUS_OK) {
		$participant = $this->createMock(Participant::class);
		$participant->expects($this->once())
			->method('setNotificationLevel')
			->with($level)
			->willReturn($validSet);

		$room = $this->createMock(Room::class);

		$controller = $this->getController($room, $participant);
		$expected = new DataResponse([], $status);

		$this->assertEquals($expected, $controller->setNotificationLevel($level));
	}
}

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
use OCA\Talk\Config;
use OCA\Talk\Controller\RoomController;
use OCA\Talk\GuestManager;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCA\Talk\TalkSession;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\UserStatus\IManager as IUserStatusManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class RoomControllerTest extends TestCase {

	/** @var string */
	private $userId;
	/** @var IAppManager|MockObject */
	private $appManager;
	/** @var TalkSession|MockObject */
	private $talkSession;
	/** @var IUserManager|MockObject */
	protected $userManager;
	/** @var IGroupManager|MockObject */
	protected $groupManager;
	/** @var IUserStatusManager|MockObject */
	protected $statusManager;
	/** @var Manager|MockObject */
	protected $manager;
	/** @var RoomService|MockObject */
	protected $roomService;
	/** @var ChatManager|MockObject */
	protected $chatManager;
	/** @var GuestManager|MockObject */
	protected $guestManager;
	/** @var IEventDispatcher|MockObject */
	protected $dispatcher;
	/** @var MessageParser|MockObject */
	protected $messageParser;
	/** @var ITimeFactory|MockObject */
	protected $timeFactory;
	/** @var IL10N|MockObject */
	private $l;
	/** @var IConfig|MockObject */
	private $serverConfig;
	/** @var Config|MockObject */
	private $talkConfig;


	public function setUp(): void {
		parent::setUp();

		$this->userId = 'testUser';
		$this->appManager = $this->createMock(IAppManager::class);
		$this->talkSession = $this->createMock(TalkSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->manager = $this->createMock(Manager::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->guestManager = $this->createMock(GuestManager::class);
		$this->statusManager = $this->createMock(IUserStatusManager::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->l = $this->createMock(IL10N::class);
		$this->serverConfig = $this->createMock(IConfig::class);
		$this->talkConfig = $this->createMock(Config::class);
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
			$this->appManager,
			$this->talkSession,
			$this->userManager,
			$this->groupManager,
			$this->manager,
			$this->roomService,
			$this->guestManager,
			$this->statusManager,
			$this->chatManager,
			$this->dispatcher,
			$this->messageParser,
			$this->timeFactory,
			$this->l,
			$this->serverConfig,
			$this->talkConfig
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

<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Config;
use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\Session;
use OCA\Talk\Model\SessionMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\MembershipService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\SessionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudIdManager;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use OCP\UserStatus\IManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * @group DB
 */
class ParticipantServiceTest extends TestCase {
	/** @var IConfig|MockObject */
	protected $serverConfig;
	/** @var Config|MockObject */
	protected $talkConfig;
	protected ?AttendeeMapper $attendeeMapper = null;
	protected ?SessionMapper $sessionMapper = null;
	/** @var SessionService|MockObject */
	protected $sessionService;
	/** @var ISecureRandom|MockObject */
	protected $secureRandom;
	/** @var IEventDispatcher|MockObject */
	protected $dispatcher;
	/** @var IUserManager|MockObject */
	protected $userManager;
	protected ICloudIdManager|MockObject $cloudIdManager;
	/** @var IGroupManager|MockObject */
	protected $groupManager;
	/** @var MembershipService|MockObject */
	protected $membershipService;
	/** @var BackendNotifier|MockObject */
	protected $federationBackendNotifier;
	/** @var ITimeFactory|MockObject */
	protected $time;
	/** @var ICacheFactory|MockObject */
	protected $cacheFactory;
	/** @var IManager|MockObject */
	protected $userStatusManager;
	/** @var LoggerInterface|MockObject */
	protected $logger;
	private ?ParticipantService $service = null;


	public function setUp(): void {
		parent::setUp();

		$this->serverConfig = $this->createMock(IConfig::class);
		$this->talkConfig = $this->createMock(Config::class);
		$this->attendeeMapper = new AttendeeMapper(\OC::$server->getDatabaseConnection());
		$this->sessionMapper = new SessionMapper(\OC::$server->getDatabaseConnection());
		$this->sessionService = $this->createMock(SessionService::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->membershipService = $this->createMock(MembershipService::class);
		$this->federationBackendNotifier = $this->createMock(BackendNotifier::class);
		$this->time = $this->createMock(ITimeFactory::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->userStatusManager = $this->createMock(IManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new ParticipantService(
			$this->serverConfig,
			$this->talkConfig,
			$this->attendeeMapper,
			$this->sessionMapper,
			$this->sessionService,
			$this->secureRandom,
			\OC::$server->getDatabaseConnection(),
			$this->dispatcher,
			$this->userManager,
			$this->cloudIdManager,
			$this->groupManager,
			$this->membershipService,
			$this->federationBackendNotifier,
			$this->time,
			$this->cacheFactory,
			$this->userStatusManager,
			$this->logger,
		);
	}

	public function tearDown(): void {
		try {
			$attendee = $this->attendeeMapper->findByActor(123456789, Attendee::ACTOR_USERS, 'test');
			$this->sessionMapper->deleteByAttendeeId($attendee->getId());
			$this->attendeeMapper->delete($attendee);
		} catch (DoesNotExistException $exception) {
		}

		parent::tearDown();
	}

	public function testGetParticipantsByNotificationLevel(): void {
		$attendee = new Attendee();
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId('test');
		$attendee->setRoomId(123456789);
		$attendee->setNotificationLevel(Participant::NOTIFY_MENTION);
		$this->attendeeMapper->insert($attendee);

		$session1 = new Session();
		$session1->setAttendeeId($attendee->getId());
		$session1->setSessionId(self::getUniqueID('session1'));
		$this->sessionMapper->insert($session1);

		$session2 = new Session();
		$session2->setAttendeeId($attendee->getId());
		$session2->setSessionId(self::getUniqueID('session2'));
		$this->sessionMapper->insert($session2);

		$room = $this->createMock(Room::class);
		$room->method('getId')
			->willReturn(123456789);
		$participants = $this->service->getParticipantsByNotificationLevel($room, Participant::NOTIFY_MENTION);
		self::assertCount(1, $participants);
	}
}

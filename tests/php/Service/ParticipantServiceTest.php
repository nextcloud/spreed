<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
use OCP\IDBConnection;
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
	protected IConfig&MockObject $serverConfig;
	protected Config&MockObject $talkConfig;
	protected ?AttendeeMapper $attendeeMapper = null;
	protected ?SessionMapper $sessionMapper = null;
	protected SessionService&MockObject $sessionService;
	protected ISecureRandom&MockObject $secureRandom;
	protected IEventDispatcher&MockObject $dispatcher;
	protected IUserManager&MockObject $userManager;
	protected ICloudIdManager&MockObject $cloudIdManager;
	protected IGroupManager&MockObject $groupManager;
	protected MembershipService&MockObject $membershipService;
	protected BackendNotifier&MockObject $federationBackendNotifier;
	protected ITimeFactory&MockObject $time;
	protected ICacheFactory&MockObject $cacheFactory;
	protected IManager&MockObject $userStatusManager;
	private ?ParticipantService $service = null;
	protected LoggerInterface&MockObject $logger;


	public function setUp(): void {
		parent::setUp();

		$this->serverConfig = $this->createMock(IConfig::class);
		$this->talkConfig = $this->createMock(Config::class);
		$this->attendeeMapper = new AttendeeMapper(\OCP\Server::get(IDBConnection::class));
		$this->sessionMapper = new SessionMapper(\OCP\Server::get(IDBConnection::class));
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
			\OCP\Server::get(IDBConnection::class),
			$this->dispatcher,
			$this->userManager,
			$this->cloudIdManager,
			$this->groupManager,
			$this->membershipService,
			$this->federationBackendNotifier,
			$this->time,
			$this->cacheFactory,
			$this->userStatusManager,
			$this->logger
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

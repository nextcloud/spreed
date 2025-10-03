<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\SessionMapper;
use OCA\Talk\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

#[Group('DB')]
class SessionServiceTest extends TestCase {
	protected ?SessionMapper $sessionMapper = null;
	protected ISecureRandom&MockObject $secureRandom;
	protected ITimeFactory&MockObject $timeFactory;
	private ?SessionService $service = null;

	private const RANDOM_254 = '123456789abcdef0123456789abcdef1123456789abcdef2123456789abcdef3123456789abcdef4123456789abcdef5123456789abcdef6123456789abcdef7123456789abcdef8123456789abcdef9123456789abcdefa123456789abcdefb123456789abcdefc123456789abcdefd123456789abcdefe123456789abcde';

	private array $attendeeIds = [];

	public function setUp(): void {
		parent::setUp();

		$this->sessionMapper = \OCP\Server::get(SessionMapper::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->service = new SessionService(
			$this->sessionMapper,
			\OCP\Server::get(IDBConnection::class),
			$this->secureRandom,
			$this->timeFactory,
		);
	}

	public function tearDown(): void {
		foreach ($this->attendeeIds as $attendeeId) {
			try {
				$this->sessionMapper->deleteByAttendeeId($attendeeId);
			} catch (DoesNotExistException $exception) {
			}
		}

		parent::tearDown();
	}

	public function testCreateSessionForAttendee() {
		$attendee = new Attendee();
		$attendee->setId(42);
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId('test');
		$this->attendeeIds[] = $attendee->getId();

		$random = self::RANDOM_254 . 'x';

		$this->secureRandom->expects($this->once())
			->method('generate')
			->with(255)
			->willReturn($random);

		$session = $this->service->createSessionForAttendee($attendee);

		self::assertEquals($random, $session->getSessionId());
	}

	public function testCreateSessionForAttendeeWithDuplicatedSessionId() {
		$attendee1 = new Attendee();
		$attendee1->setId(42);
		$attendee1->setActorType(Attendee::ACTOR_USERS);
		$attendee1->setActorId('test1');
		$this->attendeeIds[] = $attendee1->getId();

		$attendee2 = new Attendee();
		$attendee2->setId(108);
		$attendee2->setActorType(Attendee::ACTOR_USERS);
		$attendee2->setActorId('test2');
		$this->attendeeIds[] = $attendee2->getId();

		$random1 = self::RANDOM_254 . 'x';
		$random2 = self::RANDOM_254 . 'y';

		$this->secureRandom->expects($this->exactly(3))
			->method('generate')
			->with(255)
			->willReturn(
				$random1,
				$random1,
				$random2,
			);

		$session1 = $this->service->createSessionForAttendee($attendee1);
		$session2 = $this->service->createSessionForAttendee($attendee2);

		self::assertEquals($random1, $session1->getSessionId());
		self::assertEquals($random2, $session2->getSessionId());
	}

	public function testCreateSessionForAttendeeWithoutId() {
		$attendee = new Attendee();
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId('test');

		$random = self::RANDOM_254 . 'x';

		$this->secureRandom->expects($this->once())
			->method('generate')
			->with(255)
			->willReturn($random);

		$this->expectException(\OC\DB\Exceptions\DbalException::class);

		$session = $this->service->createSessionForAttendee($attendee);
	}

	public function testCreateSessionForAttendeeWithInvitedCloudId() {
		$attendee = new Attendee();
		$attendee->setId(42);
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId('test');
		$this->attendeeIds[] = $attendee->getId();

		$random = self::RANDOM_254 . 'x';

		$this->secureRandom->expects($this->once())
			->method('generate')
			->with(255)
			->willReturn($random);

		$cloudId = 'user@server.com';
		$attendee->setInvitedCloudId($cloudId);

		$session = $this->service->createSessionForAttendee($attendee);

		self::assertEquals($random . '#' . $cloudId, $session->getSessionId());
	}

	public function testExtendSessionIdWithMaximumLengthCloudId(): void {
		$attendee = new Attendee();
		$attendee->setId(42);
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId('test');
		$this->attendeeIds[] = $attendee->getId();

		$random = self::RANDOM_254 . 'x';

		$this->secureRandom->expects($this->once())
			->method('generate')
			->with(255)
			->willReturn($random);

		// User ids are 64 characters long at most; total cloud id length needs
		// to leave room for the '#' joining the ids.
		$cloudId = 'user123456789abcdef0123456789abcdef1123456789abcdef2123456789abc@server123456789abcdef0123456789abcdef1123456789abcdef2123456789abcdef3123456789abcdef4123456789abcdef5123456789abcdef6123456789abcdef7123456789abcdef8123456789abcdef9123456789abcdefa12345.com';
		$attendee->setInvitedCloudId($cloudId);

		$session = $this->service->createSessionForAttendee($attendee);

		self::assertEquals(256, strlen($cloudId));
		self::assertEquals(512, strlen($session->getSessionId()));
		self::assertEquals($random . '#' . $cloudId, $session->getSessionId());
	}

	public function testExtendSessionIdWithTooLongCloudId(): void {
		$attendee = new Attendee();
		$attendee->setId(42);
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId('test');
		$this->attendeeIds[] = $attendee->getId();

		$random = self::RANDOM_254 . 'x';

		$this->secureRandom->expects($this->once())
			->method('generate')
			->with(255)
			->willReturn($random);

		// User ids are 64 characters long at most; total cloud id length needs
		// to leave room for the '#' joining the ids.
		$cloudId = 'user123456789abcdef0123456789abcdef1123456789abcdef2123456789abc@server123456789abcdef0123456789abcdef1123456789abcdef2123456789abcdef3123456789abcdef4123456789abcdef5123456789abcdef6123456789abcdef7123456789abcdef8123456789abcdef9123456789abcdefa123456.com';
		$trimmedCloudId = 'user123456789abcdef0123456789abcdef1123456789abcdef2123456789abc@server123456789abcdef0123456789abcdef1123456789abcdef2123456789abcdef3123456789abcdef4123456789abcdef5123456789abcdef6123456789abcdef7123456789abcdef8123456789abcdef9123456789abcdefa123456.co';
		$attendee->setInvitedCloudId($cloudId);

		$session = $this->service->createSessionForAttendee($attendee);

		self::assertEquals(257, strlen($cloudId));
		self::assertEquals(512, strlen($session->getSessionId()));
		self::assertEquals($random . '#' . $trimmedCloudId, $session->getSessionId());
	}
}

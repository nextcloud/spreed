<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Ban;
use OCA\Talk\Model\BanMapper;
use OCA\Talk\Room;
use OCA\Talk\Service\BanService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Security\Ip\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class BanServiceTest extends TestCase {
	protected BanMapper&MockObject $banMapper;
	protected ParticipantService&MockObject $participantService;
	protected IUserManager&MockObject $userManager;
	protected IEventDispatcher&MockObject $dispatcher;
	protected BanService $service;

	public function setUp(): void {
		parent::setUp();

		$this->banMapper = $this->createMock(BanMapper::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->service = new BanService(
			$this->banMapper,
			$this->participantService,
			$this->userManager,
			$this->createMock(TalkSession::class),
			$this->createMock(IRequest::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IFactory::class),
			$this->dispatcher,
		);
	}

	public function testCreateBanLogsCriticalAction(): void {
		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);
		$room->method('getToken')->willReturn('abcdef');
		$this->participantService->method('getParticipantByActor')
			->willThrowException(new ParticipantNotFoundException());
		$this->banMapper->method('insert')
			->willReturnCallback(static function (Ban $ban): Ban {
				$ban->setId(12);
				return $ban;
			});

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'spreed.ban.created'
				&& $event->getLogMessage() === '{bannedActorType} "{bannedActorId}" was banned from conversation "{token}" (ban #{banId})'
				&& $event->getParameters() === ['bannedActorType' => 'users', 'bannedActorId' => 'bob', 'token' => 'abcdef', 'banId' => 12]));

		$this->service->createBan($room, Attendee::ACTOR_USERS, 'alice', 'Alice', Attendee::ACTOR_USERS, 'bob', new \DateTime(), 'Internal note');
	}

	public function testDeleteBanLogsCriticalAction(): void {
		$ban = new Ban();
		$ban->setBannedActorType(Attendee::ACTOR_USERS);
		$ban->setBannedActorId('bob');
		$this->banMapper->method('findByBanIdAndRoom')
			->with(12, 34)
			->willReturn($ban);

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'spreed.ban.deleted'
				&& $event->getParameters() === ['banId' => 12, 'bannedActorType' => 'users', 'bannedActorId' => 'bob', 'roomId' => 34]));

		$this->service->findAndDeleteBanByIdForRoom(12, 34);
	}

	public function testDeleteUnknownBanIsNotLogged(): void {
		$this->banMapper->method('findByBanIdAndRoom')
			->willThrowException(new DoesNotExistException('not found'));

		$this->dispatcher->expects($this->never())
			->method('dispatchTyped');

		$this->service->findAndDeleteBanByIdForRoom(12, 34);
	}
}

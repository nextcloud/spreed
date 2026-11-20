<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Federation;

use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\Federation\RestrictionValidator;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\Invitation;
use OCA\Talk\Model\InvitationMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\IUser;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class FederationManagerTest extends TestCase {
	protected Manager&MockObject $manager;
	protected ParticipantService&MockObject $participantService;
	protected InvitationMapper&MockObject $invitationMapper;
	protected AttendeeMapper&MockObject $attendeeMapper;
	protected BackendNotifier&MockObject $backendNotifier;
	protected ICloudIdManager&MockObject $cloudIdManager;
	protected IEventDispatcher&MockObject $dispatcher;
	protected FederationManager $federationManager;
	protected IUser&MockObject $user;

	public function setUp(): void {
		parent::setUp();

		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->invitationMapper = $this->createMock(InvitationMapper::class);
		$this->attendeeMapper = $this->createMock(AttendeeMapper::class);
		$this->backendNotifier = $this->createMock(BackendNotifier::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$notificationManager = $this->createMock(IManager::class);
		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notificationManager->method('createNotification')->willReturn($notification);

		$this->federationManager = new FederationManager(
			$this->manager,
			$this->participantService,
			$this->createMock(RoomService::class),
			$this->invitationMapper,
			$this->attendeeMapper,
			$this->backendNotifier,
			$notificationManager,
			$this->cloudIdManager,
			$this->createMock(RestrictionValidator::class),
			$this->dispatcher,
		);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('alice');
		$this->user->method('getDisplayName')->willReturn('Alice');
	}

	protected function createInvitation(int $state): Invitation {
		$invitation = new Invitation();
		$invitation->setId(5);
		$invitation->setUserId('alice');
		$invitation->setState($state);
		$invitation->setLocalRoomId(10);
		$invitation->setRemoteServerUrl('https://remote.example');
		$invitation->setRemoteToken('remotetoken');
		$invitation->setRemoteAttendeeId(3);
		$invitation->setAccessToken('access-token');
		$invitation->setLocalCloudId('alice@local.example');
		return $invitation;
	}

	public function testAcceptRemoteRoomShareLogsCriticalAction(): void {
		$this->invitationMapper->method('getInvitationById')->with(5)->willReturn($this->createInvitation(Invitation::STATE_PENDING));
		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getId')->willReturn('alice@local.example');
		$this->cloudIdManager->method('getCloudId')->willReturn($cloudId);
		$room = $this->createMock(Room::class);
		$room->method('getToken')->willReturn('localtoken');
		$this->manager->method('getRoomById')->with(10)->willReturn($room);
		$this->backendNotifier->method('sendShareAccepted')->willReturn(true);
		$this->participantService->method('addUsers')->willReturn([new Attendee()]);

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'spreed.federation_invite.accepted'
				&& $event->getLogMessage() === 'Invitation #{invitationId} to federated conversation "{remoteToken}" on "{remoteServerUrl}" was accepted'
				&& $event->getParameters() === [
					'invitationId' => 5,
					'remoteToken' => 'remotetoken',
					'remoteServerUrl' => 'https://remote.example',
					'token' => 'localtoken',
				]));

		$this->federationManager->acceptRemoteRoomShare($this->user, 5);
	}

	public function testRejectRemoteRoomShareLogsCriticalAction(): void {
		$this->invitationMapper->method('getInvitationById')->with(5)->willReturn($this->createInvitation(Invitation::STATE_PENDING));

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'spreed.federation_invite.rejected'
				&& $event->getParameters() === [
					'invitationId' => 5,
					'remoteToken' => 'remotetoken',
					'remoteServerUrl' => 'https://remote.example',
				]));

		$this->federationManager->rejectRemoteRoomShare($this->user, 5);
	}

	public function testRejectAcceptedInvitationIsNotLogged(): void {
		$this->invitationMapper->method('getInvitationById')->willReturn($this->createInvitation(Invitation::STATE_ACCEPTED));

		$this->dispatcher->expects($this->never())->method('dispatchTyped');
		$this->expectException(\InvalidArgumentException::class);

		$this->federationManager->rejectRemoteRoomShare($this->user, 5);
	}
	protected function prepareReInvite(Invitation $invitation): void {
		$room = $this->createMock(Room::class);
		$this->manager->method('getRoomByToken')->with('remotetoken', null, 'https://remote.example')->willReturn($room);
		$this->participantService->method('getNumberOfActors')->willReturn(1);
		$this->invitationMapper->method('getInvitationForUserByLocalRoom')->with($room, 'alice', true)->willReturn($invitation);
		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getId')->willReturn('alice@local.example');
		$this->cloudIdManager->method('getCloudId')->willReturn($cloudId);
	}

	public function testReInviteForAcceptedInvitationIsRejectedWhenHostDoesNotConfirm(): void {
		$invitation = $this->createInvitation(Invitation::STATE_ACCEPTED);
		$this->prepareReInvite($invitation);

		$this->backendNotifier->expects($this->once())
			->method('sendShareAccepted')
			->with('https://remote.example', 42, 'forged-secret', 'Alice', 'alice@local.example')
			->willReturn(false);
		$this->invitationMapper->expects($this->never())->method('update');
		$this->attendeeMapper->expects($this->never())->method('update');

		try {
			$this->federationManager->addRemoteRoom($this->user, 42, Room::TYPE_GROUP, 'Room', Attendee::PERMISSIONS_DEFAULT, 'remotetoken', 'https://remote.example', 'forged-secret', 'mallory@remote.example', 'Mallory', 'alice@local.example');
			$this->fail('Expected ProviderCouldNotAddShareException');
		} catch (ProviderCouldNotAddShareException) {
		}

		$this->assertSame('access-token', $invitation->getAccessToken());
		$this->assertSame(3, $invitation->getRemoteAttendeeId());
	}

	public function testReInviteForAcceptedInvitationUpdatesCredentialsWhenHostConfirms(): void {
		$invitation = $this->createInvitation(Invitation::STATE_ACCEPTED);
		$this->prepareReInvite($invitation);

		$attendee = new Attendee();
		$attendee->setAccessToken('access-token');
		$attendee->setRemoteId('3');
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);
		$this->participantService->method('getParticipantByActor')->willReturn($participant);

		$this->backendNotifier->expects($this->once())
			->method('sendShareAccepted')
			->with('https://remote.example', 42, 'new-secret', 'Alice', 'alice@local.example')
			->willReturn(true);
		$this->attendeeMapper->expects($this->once())->method('update')->with($attendee);
		$this->invitationMapper->expects($this->once())->method('update')->with($invitation);

		$this->federationManager->addRemoteRoom($this->user, 42, Room::TYPE_GROUP, 'Room', Attendee::PERMISSIONS_DEFAULT, 'remotetoken', 'https://remote.example', 'new-secret', 'bob@remote.example', 'Bob', 'alice@local.example');

		$this->assertSame('new-secret', $invitation->getAccessToken());
		$this->assertSame(42, $invitation->getRemoteAttendeeId());
		$this->assertSame(Invitation::STATE_ACCEPTED, $invitation->getState());
		$this->assertSame('new-secret', $attendee->getAccessToken());
		$this->assertSame('42', $attendee->getRemoteId());
	}

	public function testReInviteForPendingInvitationDoesNotContactHost(): void {
		$invitation = $this->createInvitation(Invitation::STATE_PENDING);
		$this->prepareReInvite($invitation);

		$this->backendNotifier->expects($this->never())->method('sendShareAccepted');
		$this->invitationMapper->expects($this->once())->method('update')->with($invitation);

		$this->federationManager->addRemoteRoom($this->user, 42, Room::TYPE_GROUP, 'Room', Attendee::PERMISSIONS_DEFAULT, 'remotetoken', 'https://remote.example', 'new-secret', 'bob@remote.example', 'Bob', 'alice@local.example');

		$this->assertSame('new-secret', $invitation->getAccessToken());
	}
}

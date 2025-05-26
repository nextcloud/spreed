<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Collaboration\Resources;

use OCA\Talk\Collaboration\Resources\ConversationProvider;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCP\Collaboration\Resources\IResource;
use OCP\Collaboration\Resources\ResourceException;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ConversationProviderTest extends TestCase {
	protected Manager&MockObject $manager;
	protected AvatarService&MockObject $avatarService;
	protected ParticipantService&MockObject $participantService;
	protected IUserSession&MockObject $userSession;
	protected IURLGenerator&MockObject $urlGenerator;
	protected ?ConversationProvider $provider = null;

	public function setUp(): void {
		parent::setUp();

		$this->manager = $this->createMock(Manager::class);
		$this->avatarService = $this->createMock(AvatarService::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->provider = new ConversationProvider(
			$this->manager,
			$this->avatarService,
			$this->participantService,
			$this->userSession,
			$this->urlGenerator
		);
	}

	public function testCanAccessResourceThrowsGuest(): void {
		$resource = $this->createMock(IResource::class);

		$this->expectException(ResourceException::class);
		$this->expectExceptionMessage('Guests are not supported at the moment');
		$this->provider->canAccessResource($resource, null);
	}

	public function testCanAccessResourceThrowsRoom(): void {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getUID')
			->willReturn('uid');
		$resource = $this->createMock(IResource::class);
		$resource->expects($this->once())
			->method('getId')
			->willReturn('token');

		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->with('token', 'uid')
			->willThrowException(new RoomNotFoundException());

		$this->expectExceptionMessage('Conversation not found');
		$this->provider->canAccessResource($resource, $user);
	}

	public function testCanAccessResourceThrowsParticipant(): void {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getUID')
			->willReturn('uid');
		$resource = $this->createMock(IResource::class);
		$resource->expects($this->once())
			->method('getId')
			->willReturn('token');
		$room = $this->createMock(Room::class);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->with($room, 'uid')
			->willThrowException(new ParticipantNotFoundException());

		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->with('token', 'uid')
			->willReturn($room);

		$this->expectExceptionMessage('Participant not found');
		$this->provider->canAccessResource($resource, $user);
	}

	public function testCanAccessResourceParticipantNotAdded(): void {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getUID')
			->willReturn('uid');
		$resource = $this->createMock(IResource::class);
		$resource->expects($this->once())
			->method('getId')
			->willReturn('token');

		$participant = $this->createMock(Participant::class);
		$attendee = Attendee::fromRow([
			'actor_type' => 'users',
			'actor_id' => 'uid',
			'participant_type' => Participant::USER_SELF_JOINED,
		]);
		$participant->expects($this->any())
			->method('getAttendee')
			->willReturn($attendee);
		$room = $this->createMock(Room::class);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->with($room, 'uid')
			->willReturn($participant);

		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->with('token', 'uid')
			->willReturn($room);

		$this->assertFalse($this->provider->canAccessResource($resource, $user));
	}

	public static function dataCanAccessResourceYes(): array {
		return [
			[Participant::OWNER],
			[Participant::MODERATOR],
			[Participant::USER],
		];
	}

	#[DataProvider('dataCanAccessResourceYes')]
	public function testCanAccessResourceYes(int $participantType): void {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getUID')
			->willReturn('uid');
		$resource = $this->createMock(IResource::class);
		$resource->expects($this->once())
			->method('getId')
			->willReturn('token');

		$participant = $this->createMock(Participant::class);
		$attendee = Attendee::fromRow([
			'actor_type' => 'users',
			'actor_id' => 'uid',
			'participant_type' => $participantType,
		]);
		$participant->expects($this->any())
			->method('getAttendee')
			->willReturn($attendee);
		$room = $this->createMock(Room::class);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->with($room, 'uid')
			->willReturn($participant);

		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->with('token', 'uid')
			->willReturn($room);

		$this->assertTrue($this->provider->canAccessResource($resource, $user));
	}
}

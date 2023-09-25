<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ConversationProviderTest extends TestCase {
	/** @var Manager|MockObject */
	protected $manager;
	/** @var AvatarService|MockObject */
	protected $avatarService;
	/** @var ParticipantService|MockObject */
	protected $participantService;
	/** @var IUserSession|MockObject */
	protected $userSession;
	/** @var IURLGenerator|MockObject */
	protected $urlGenerator;
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

	/**
	 * @dataProvider dataCanAccessResourceYes
	 * @param int $participantType
	 */
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

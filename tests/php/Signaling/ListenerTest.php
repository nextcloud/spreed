<?php
/**
 *
 * @copyright Copyright (c) 2018, Joachim Bauch (bauch@struktur.de)
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

namespace OCA\Talk\Tests\php\Signaling;

use OCA\Talk\Config;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\BeforeRoomDeletedEvent;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\GuestsCleanedUpEvent;
use OCA\Talk\Events\LobbyModifiedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Events\SystemMessagesMultipleSentEvent;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\SessionService;
use OCA\Talk\Signaling\BackendNotifier;
use OCA\Talk\Signaling\Listener;
use OCA\Talk\Signaling\Messages;
use OCA\Talk\Webinary;
use OCP\Comments\IComment;
use Test\TestCase;

/**
 * @group DB
 */
class ListenerTest extends TestCase {
	protected ?BackendNotifier $backendNotifier = null;
	protected ?Manager $manager = null;
	protected ?ParticipantService $participantService = null;
	protected ?SessionService $sessionService = null;
	protected ?Listener $listener = null;

	public function setUp(): void {
		parent::setUp();

		$this->backendNotifier = $this->createMock(BackendNotifier::class);
		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->sessionService = $this->createMock(SessionService::class);

		$this->listener = new Listener(
			$this->createMock(Config::class),
			$this->createMock(Messages::class),
			$this->backendNotifier,
			$this->manager,
			$this->participantService,
			$this->sessionService,
		);
	}

	public function dataRoomModified(): array {
		return [
			[
				ARoomModifiedEvent::PROPERTY_NAME,
				'Test room',
				'name',
			],
			[
				ARoomModifiedEvent::PROPERTY_DESCRIPTION,
				'The description',
				'',
			],
			[
				ARoomModifiedEvent::PROPERTY_PASSWORD,
				'password',
				null,
			],
			[
				ARoomModifiedEvent::PROPERTY_TYPE,
				Room::TYPE_PUBLIC,
				Room::TYPE_GROUP,
			],
			[
				ARoomModifiedEvent::PROPERTY_READ_ONLY,
				Room::READ_ONLY,
				Room::READ_WRITE,
			],
			[
				ARoomModifiedEvent::PROPERTY_LISTABLE,
				Room::LISTABLE_ALL,
				Room::LISTABLE_NONE,
			],
		];
	}

	/**
	 * @dataProvider dataRoomModified
	 * @param string $property
	 * @param mixed $newValue
	 * @param mixed $oldValue
	 */
	public function testRoomModified(string $property, mixed $newValue, mixed $oldValue): void {
		$room = $this->createMock(Room::class);

		$event = new RoomModifiedEvent(
			$room,
			$property,
			$newValue,
			$oldValue,
			null,
		);

		$this->backendNotifier->expects($this->once())
			->method('roomModified')
			->with($room);
		$this->listener->handle($event);
	}

	public function testRecordingStatusChanged(): void {
		$room = $this->createMock(Room::class);

		$event = new RoomModifiedEvent(
			$room,
			ARoomModifiedEvent::PROPERTY_CALL_RECORDING,
			Room::RECORDING_VIDEO,
			Room::RECORDING_NONE,
			null,
		);

		$this->backendNotifier->expects($this->once())
			->method('sendRoomMessage')
			->with($room, [
				'type' => 'recording',
				'recording' => [
					'status' => $event->getNewValue(),
				],
			]);
		$this->listener->handle($event);
	}

	public function dataRoomLobbyModified(): array {
		return [
			[
				Webinary::LOBBY_NON_MODERATORS,
				Webinary::LOBBY_NONE,
				null,
				true,
			],
			[
				Webinary::LOBBY_NONE,
				Webinary::LOBBY_NON_MODERATORS,
				null,
				false,
			],
			[
				Webinary::LOBBY_NONE,
				Webinary::LOBBY_NON_MODERATORS,
				new \DateTime(),
				false,
			],
		];
	}

	/**
	 * @dataProvider dataRoomLobbyModified
	 * @param int $newValue
	 * @param int $oldValue
	 * @param \DateTime|null $lobbyTimer
	 * @param bool $timerReached
	 */
	public function testRoomLobbyModified(int $newValue, int $oldValue, ?\DateTime $lobbyTimer, bool $timerReached): void {
		$room = $this->createMock(Room::class);

		$event = new LobbyModifiedEvent(
			$room,
			$newValue,
			$oldValue,
			$lobbyTimer,
			$timerReached,
		);

		$this->backendNotifier->expects($this->once())
			->method('roomModified')
			->with($room);
		$this->listener->handle($event);
	}

	public function testRoomLobbyRemoved() {
		$room = $this->createMock(Room::class);

		$event = new LobbyModifiedEvent(
			$room,
			Webinary::LOBBY_NONE,
			Webinary::LOBBY_NON_MODERATORS,
			null,
			true,
		);

		$this->backendNotifier->expects($this->once())
			->method('roomModified')
			->with($room);
		$this->listener->handle($event);
	}

	public function testRoomDelete(): void {
		$room = $this->createMock(Room::class);

		$event = new BeforeRoomDeletedEvent(
			$room
		);

		$this->participantService->method('getParticipantUserIds')
			->with($room)
			->willReturn(['user1', 'user2']);

		$this->backendNotifier->expects($this->once())
			->method('roomDeleted')
			->with($room, ['user1', 'user2']);

		$this->listener->handle($event);
	}

	public function testGuestsCleanedUpEvent(): void {
		$room = $this->createMock(Room::class);

		$event = new GuestsCleanedUpEvent(
			$room
		);

		$this->backendNotifier->expects($this->once())
			->method('participantsModified')
			->with($room, []);

		$this->listener->handle($event);
	}

	public function testChatMessageSentEvent(): void {
		$room = $this->createMock(Room::class);
		$comment = $this->createMock(IComment::class);

		$event = new ChatMessageSentEvent(
			$room,
			$comment,
		);

		$this->backendNotifier->expects($this->once())
			->method('sendRoomMessage')
			->with($room, [
				'type' => 'chat',
				'chat' => [
					'refresh' => true,
				],
			]);

		$this->listener->handle($event);
	}

	public function testSystemMessageSentEvent(): void {
		$room = $this->createMock(Room::class);
		$comment = $this->createMock(IComment::class);

		$event = new SystemMessageSentEvent(
			$room,
			$comment,
			skipLastActivityUpdate: false
		);

		$this->backendNotifier->expects($this->once())
			->method('sendRoomMessage')
			->with($room, [
				'type' => 'chat',
				'chat' => [
					'refresh' => true,
				],
			]);

		$this->listener->handle($event);
	}

	public function testSystemMessageSentEventSkippingUpdate(): void {
		$room = $this->createMock(Room::class);
		$comment = $this->createMock(IComment::class);

		$event = new SystemMessageSentEvent(
			$room,
			$comment,
			skipLastActivityUpdate: true
		);

		$this->backendNotifier->expects($this->never())
			->method('sendRoomMessage');

		$this->listener->handle($event);
	}

	public function testSystemMessagesMultipleSentEvent(): void {
		$room = $this->createMock(Room::class);
		$comment = $this->createMock(IComment::class);

		$event = new SystemMessagesMultipleSentEvent(
			$room,
			$comment,
		);

		$this->backendNotifier->expects($this->once())
			->method('sendRoomMessage')
			->with($room, [
				'type' => 'chat',
				'chat' => [
					'refresh' => true,
				],
			]);

		$this->listener->handle($event);
	}
}

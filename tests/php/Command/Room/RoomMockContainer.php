<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Daniel Rudolf <nextcloud.com@daniel-rudolf.de>
 *
 * @author Daniel Rudolf <nextcloud.com@daniel-rudolf.de>
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

namespace OCA\Talk\Tests\php\Command\Room;

use InvalidArgumentException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RoomMockContainer {
	/** @var TestCase */
	private $testCase;

	/** @var callable[] */
	private $callbacks = [];

	/** @var Room|null */
	private $room;

	/** @var array|null */
	private $roomData;

	/** @var Participant[] */
	private $participants = [];

	/** @var array[] */
	private $participantData = [];

	public function __construct(TestCase $testCase) {
		$this->testCase = $testCase;
	}

	public function create(array $data = []): Room {
		if ($this->room !== null) {
			throw new InvalidArgumentException(__METHOD__ . ' must not be called multiple times.');
		}

		/** @var Room|MockObject $room */
		$room = $this->createMock(Room::class);

		$this->room = $room;
		$this->roomData = self::prepareRoomData($data);

		// simple getter
		foreach (['token', 'name', 'type', 'readOnly'] as $key) {
			$room->method('get' . ucfirst($key))
				->willReturnCallback(function () use ($key) {
					return $this->roomData[$key];
				});
		}

		// simple setter
		foreach (['name', 'type', 'readOnly', 'password'] as $key) {
			$room->method('set' . ucfirst($key))
				->willReturnCallback(function ($value) use ($key): bool {
					$this->roomData[$key] = $value;
					return true;
				});
		}

		// password
		$room->method('hasPassword')
			->willReturnCallback(function (): bool {
				return $this->roomData['password'] !== '';
			});

		$room->method('verifyPassword')
			->willReturnCallback(function (string $password): array {
				return [
					'result' => in_array($this->roomData['password'], ['', $password], true),
					'url' => '',
				];
			});

		// participants
		$room->method('getParticipants')
			->willReturnCallback(function (): array {
				return $this->participants;
			});

		$room->method('getParticipant')
			->willReturnCallback(function (?string $userId): Participant {
				if (in_array($userId, [null, ''], true)) {
					throw new ParticipantNotFoundException('Not a user');
				}
				if (!isset($this->participants[$userId])) {
					throw new ParticipantNotFoundException('User is not a participant');
				}

				return $this->participants[$userId];
			});

		$room->method('addUsers')
			->willReturnCallback(function (array ...$participants): void {
				foreach ($participants as $participant) {
					$userId = $participant['userId'];
					$participantType = $participant['participantType'] ?? Participant::USER;

					$this->createParticipant($userId, ['participantType' => $participantType]);
				}
			});

		$room->method('removeUser')
			->willReturnCallback(function (IUser $user): void {
				$this->removeParticipant($user->getUID());
			});

		$room->method('setParticipantType')
			->willReturnCallback(function (Participant $participant, int $participantType): void {
				$userId = $participant->getUser();
				$this->updateParticipant($userId, ['participantType' => $participantType]);
			});

		// add participants
		foreach ($this->roomData['participants'] as $participant) {
			$userId = $participant['userId'];
			$participantType = $participant['participantType'] ?? Participant::USER;

			$this->createParticipant($userId, ['participantType' => $participantType]);
		}

		unset($this->roomData['participants']);

		// execute callbacks
		foreach ($this->callbacks as $callback) {
			$callback($room);
		}

		return $room;
	}

	protected function createParticipant(string $userId, array $data): Participant {
		/** @var Participant|MockObject $participant */
		$participant = $this->createMock(Participant::class);

		$this->participants[$userId] = $participant;
		$this->participantData[$userId] = ['userId' => $userId] + $data;

		$participant->method('getUser')
			->willReturnCallback(function () use ($userId): string {
				return $this->participantData[$userId]['userId'];
			});

		$participant->method('getParticipantType')
			->willReturnCallback(function () use ($userId): int {
				return $this->participantData[$userId]['participantType'];
			});

		return $participant;
	}

	protected function updateParticipant(string $userId, array $data): void {
		$this->participantData[$userId] = array_merge($this->participantData[$userId], $data);
	}

	protected function removeParticipant(string $userId): void {
		unset($this->participants[$userId], $this->participantData[$userId]);
	}

	public function registerCallback(callable $callback): void {
		$this->callbacks[] = $callback;
	}

	protected function createMock(string $originalClassName): MockObject {
		return $this->testCase->getMockBuilder($originalClassName)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->disableAutoReturnValueGeneration()
			->getMock();
	}

	public function getRoom(): ?Room {
		return $this->room;
	}

	public function getRoomData(): ?array {
		$participants = array_values($this->participantData);
		return $this->roomData + ['participants' => $participants];
	}

	public static function prepareRoomData(array $data): array {
		$data += [
			'token' => '__test-room',
			'name' => 'PHPUnit Test Room',
			'type' => Room::GROUP_CALL,
			'readOnly' => Room::READ_WRITE,
			'password' => '',
			'participants' => [],
		];

		return $data;
	}
}

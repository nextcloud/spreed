<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Service;

use InvalidArgumentException;
use OCA\Talk\Events\ModifyRoomEvent;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;

class RoomService {

	/** @var Manager */
	protected $manager;
	/** @var ParticipantService */
	protected $participantService;
	/** @var IEventDispatcher */
	private $dispatcher;

	public function __construct(Manager $manager,
								ParticipantService $participantService,
								IEventDispatcher $dispatcher) {
		$this->manager = $manager;
		$this->participantService = $participantService;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * @param IUser $actor
	 * @param IUser $targetUser
	 * @return Room
	 * @throws InvalidArgumentException when both users are the same
	 */
	public function createOneToOneConversation(IUser $actor, IUser $targetUser): Room {
		if ($actor->getUID() === $targetUser->getUID()) {
			throw new InvalidArgumentException('invalid_invitee');
		}

		try {
			// If room exists: Reuse that one, otherwise create a new one.
			$room = $this->manager->getOne2OneRoom($actor->getUID(), $targetUser->getUID());
			$this->participantService->ensureOneToOneRoomIsFilled($room);
		} catch (RoomNotFoundException $e) {
			$users = [$actor->getUID(), $targetUser->getUID()];
			sort($users);
			$room = $this->manager->createRoom(Room::TYPE_ONE_TO_ONE, json_encode($users));

			$this->participantService->addUsers($room, [
				[
					'actorType' => Attendee::ACTOR_USERS,
					'actorId' => $actor->getUID(),
					'displayName' => $actor->getDisplayName(),
					'participantType' => Participant::OWNER,
				],
				[
					'actorType' => Attendee::ACTOR_USERS,
					'actorId' => $targetUser->getUID(),
					'displayName' => $targetUser->getDisplayName(),
					'participantType' => Participant::OWNER,
				],
			], $actor);
		}

		return $room;
	}

	/**
	 * @param int $type
	 * @param string $name
	 * @param IUser|null $owner
	 * @param string $objectType
	 * @param string $objectId
	 * @return Room
	 * @throws InvalidArgumentException on too long or empty names
	 * @throws InvalidArgumentException unsupported type
	 * @throws InvalidArgumentException invalid object data
	 */
	public function createConversation(int $type, string $name, ?IUser $owner = null, string $objectType = '', string $objectId = ''): Room {
		$name = trim($name);
		if ($name === '' || isset($name[255])) {
			throw new InvalidArgumentException('name');
		}

		if (!\in_array($type, [
			Room::TYPE_GROUP,
			Room::TYPE_PUBLIC,
			Room::TYPE_CHANGELOG,
		], true)) {
			throw new InvalidArgumentException('type');
		}

		$objectType = trim($objectType);
		if (isset($objectType[64])) {
			throw new InvalidArgumentException('object_type');
		}

		$objectId = trim($objectId);
		if (isset($objectId[64])) {
			throw new InvalidArgumentException('object_id');
		}

		if (($objectType !== '' && $objectId === '') ||
			($objectType === '' && $objectId !== '')) {
			throw new InvalidArgumentException('object');
		}

		$room = $this->manager->createRoom($type, $name, $objectType, $objectId);

		if ($owner instanceof IUser) {
			$this->participantService->addUsers($room, [[
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $owner->getUID(),
				'participantType' => Participant::OWNER,
			]], null);
		}

		return $room;
	}

	public function prepareConversationName(string $objectName): string {
		return rtrim(mb_substr(ltrim($objectName), 0, 64));
	}

	public function setPermissions(Room $room, string $level, string $method, int $permissions, bool $resetCustomPermissions): bool {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			return false;
		}

		if ($level === 'default') {
			$oldPermissions = $room->getDefaultPermissions();
		} elseif ($level === 'call') {
			$oldPermissions = $room->getCallPermissions();
		} else {
			return false;
		}

		$newPermissions = $permissions;
		if ($method === Attendee::PERMISSIONS_MODIFY_SET) {
			if ($newPermissions !== Attendee::PERMISSIONS_DEFAULT) {
				// Make sure the custom flag is set when not setting to default permissions
				$newPermissions |= Attendee::PERMISSIONS_CUSTOM;
			}
			// If we are setting a fixed set of permissions and apply that to users,
			// we can also simplify it and reset to default.
			$resetCustomPermissions = true;
		} elseif ($method === Attendee::PERMISSIONS_MODIFY_ADD) {
			$newPermissions = $oldPermissions | $newPermissions;
		} elseif ($method === Attendee::PERMISSIONS_MODIFY_REMOVE) {
			$newPermissions = $oldPermissions & ~$newPermissions;
		} else {
			return false;
		}

		$event = new ModifyRoomEvent($room, $level . 'Permissions', $newPermissions, $oldPermissions);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_PERMISSIONS_SET, $event);

		if ($resetCustomPermissions) {
			$this->participantService->updateAllPermissions($room, Attendee::PERMISSIONS_MODIFY_SET, Attendee::PERMISSIONS_DEFAULT);
		} else {
			$this->participantService->updateAllPermissions($room, $method, $permissions);
		}

		$room->setPermissions($level, $newPermissions);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_PERMISSIONS_SET, $event);

		return true;
	}
}

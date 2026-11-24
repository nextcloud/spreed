<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Exceptions\RoomProperty\DescriptionException;
use OCA\Talk\Exceptions\RoomProperty\ListableException;
use OCA\Talk\Exceptions\RoomProperty\NameException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 *
 * @psalm-import-type TalkTeamInfo from CirclesAdapter
 */
class TeamRoomSettingsService {
	/** Same values as OCA\Circles\Model\Circle::CFG_*, which is not always available */
	public const TEAM_CFG_VISIBLE = 8;
	public const TEAM_CFG_OPEN = 16;
	public const TEAM_CFG_INVITE = 32;
	public const TEAM_CFG_REQUEST = 64;
	public const TEAM_CFG_FRIEND = 128;
	public const TEAM_CFG_ROOT = 8192;
	public const TEAM_CFG_FEDERATED = 32768;
	/** Single (1), personal (2), system (4), hidden (1024), backend (2048), app (131072), SCIM (262144) */

	public const TEAM_CFG_UNSUPPORTED = 1 | 2 | 4 | 1024 | 2048 | 131072 | 262144;

	/** Same value as OCA\Circles\Model\Member::TYPE_USER, teams are assumed to contain users only */
	public const TEAM_MEMBER_TYPE_USER = 1;
	/** Same value as OCA\Circles\Model\Member::TYPE_MAIL */
	public const TEAM_MEMBER_TYPE_MAIL = 4;

	/**
	 * Set while the team is applied to the room, so the room changes are
	 * not written back to the team (they come from the team)
	 */
	protected int $applyingTeamToRoom = 0;

	/**
	 * Set while Talk changes the team and applies the result to the room
	 * itself, so the listener of the Circles event does not apply it again
	 */
	protected int $changingTeamFromTalk = 0;

	public function __construct(
		protected readonly CirclesAdapter $circlesAdapter,
		protected readonly RoomService $roomService,
		protected readonly LoggerInterface $logger,
		protected readonly IUserSession $userSession,
	) {
	}

	public static function getListableForConfig(int $config): int {
		$joinable = ($config & self::TEAM_CFG_VISIBLE) && ($config & self::TEAM_CFG_OPEN);
		return $joinable ? Room::LISTABLE_USERS : Room::LISTABLE_NONE;
	}

	public static function getConfigForListable(int $config, int $listable): int {
		if ($listable === Room::LISTABLE_NONE) {
			return $config & ~self::TEAM_CFG_OPEN;
		}
		return $config | self::TEAM_CFG_VISIBLE | self::TEAM_CFG_OPEN;
	}

	/**
	 * Circles has no limit for descriptions, Talk has: the room shows the
	 * beginning of a longer description instead of keeping an outdated one
	 */
	public static function getDescriptionForRoom(string $description): string {
		return mb_substr(mb_trim($description), 0, Room::DESCRIPTION_MAXIMUM_LENGTH);
	}

	/**
	 * Name, description, listing and deleting of a team conversation are settings
	 * of the team: only team admins (the owners of the team room) can change
	 * them, Circles would refuse it for everyone else. Other conversations: true.
	 */
	public static function canEditTeamSettings(Room $room, ?Participant $participant): bool {
		if (!$room->isTeamManaged()) {
			return true;
		}
		return !is_null($participant)
			&& $participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS
			&& $participant->isOwner();
	}

	public static function isSupportedTeam(int $config): bool {
		return ($config & self::TEAM_CFG_UNSUPPORTED) === 0;
	}

	/**
	 * @param TalkTeamInfo|null $team Already loaded team, loaded when null
	 * @throws \Exception
	 */
	public function applyTeamToRoom(Room $room, ?array $team = null): void {
		$team ??= $this->circlesAdapter->getTeam($room->getObjectId());
		$this->applyingTeamToRoom++;
		try {
			try {
				$this->roomService->setName($room, $team['name']);
			} catch (NameException $e) {
				$this->logger->warning('Name of team ' . $team['id'] . ' is not valid for its room: ' . $e->getReason());
			}
			try {
				$this->roomService->setDescription($room, self::getDescriptionForRoom($team['description']));
			} catch (DescriptionException $e) {
				$this->logger->warning('Description of team ' . $team['id'] . ' is not valid for its room: ' . $e->getReason());
			}
			try {
				$this->roomService->setListable($room, self::getListableForConfig($team['config']));
			} catch (ListableException $e) {
				$this->logger->warning('Visibility of team ' . $team['id'] . ' is not valid for its room: ' . $e->getReason());
			}
		} finally {
			$this->applyingTeamToRoom--;
		}
	}

	/**
	 * Renaming a team room renames the team first, as $actorUserId: Circles
	 * decides the name (cleans it up, refuses names that are too short or too
	 * long, appends " (2)" when another team has the name already). The room
	 * then gets the name of the team, with one system message.
	 *
	 * @throws \Exception When the team refused the name or Circles is unavailable, the room is unchanged
	 */
	public function renameTeamRoom(Room $room, string $name, string $actorUserId): void {
		$this->changeTeamRoom($room, ['name' => $name], $actorUserId);
	}

	/**
	 * Same as renameTeamRoom() for the description: written to the team first,
	 * the room gets the description of the team. Talk's limit is checked first.
	 *
	 * @throws DescriptionException When the description is too long for Talk, nothing is changed
	 * @throws \Exception When the team refused it or Circles is unavailable, the room is unchanged
	 */
	public function setTeamRoomDescription(Room $room, string $description, string $actorUserId): void {
		$description = mb_trim($description);
		if (mb_strlen($description) > Room::DESCRIPTION_MAXIMUM_LENGTH) {
			throw new DescriptionException(DescriptionException::REASON_VALUE);
		}
		$this->changeTeamRoom($room, ['description' => $description], $actorUserId);
	}

	/**
	 * Same as renameTeamRoom() for the listing: written to the team first
	 * (visible and open, or closed), the room gets the listing of the team.
	 *
	 * @param int $listable Room::LISTABLE_*
	 * @throws ListableException When the room can not have this listing, nothing is changed
	 * @throws \Exception When the team refused it or Circles is unavailable, the room is unchanged
	 */
	public function setTeamRoomListable(Room $room, int $listable, string $actorUserId): void {
		if (!in_array($listable, [Room::LISTABLE_NONE, Room::LISTABLE_USERS], true)) {
			throw new ListableException(ListableException::REASON_VALUE);
		}
		if ($room->isClassified() && $listable !== Room::LISTABLE_NONE) {
			throw new ListableException(ListableException::REASON_CLASSIFIED);
		}

		$config = $this->circlesAdapter->getTeam($room->getObjectId())['config'];
		$newConfig = self::getConfigForListable($config, $listable);
		if ($newConfig === $config) {
			// The team has this listing already, the room only lacked it
			$this->applyTeamToRoom($room);
			return;
		}
		$this->changeTeamRoom($room, ['config' => $newConfig], $actorUserId);
	}

	/**
	 * @param array{name?: string, description?: string, config?: int} $settings
	 * @throws \Exception When the team refused it or Circles is unavailable, the room is unchanged
	 */
	protected function changeTeamRoom(Room $room, array $settings, string $actorUserId): void {
		$this->changingTeamFromTalk++;
		try {
			$this->circlesAdapter->updateTeam($room->getObjectId(), $settings, $actorUserId);
		} finally {
			$this->changingTeamFromTalk--;
		}

		try {
			$this->applyTeamToRoom($room);
		} catch (\Exception $e) {
			// The team is changed already, the next resync will update the room
			$this->logger->warning('Settings of team ' . $room->getObjectId() . ' could not be applied to its team room yet', ['exception' => $e]);
		}
	}

	/**
	 * Whether Talk changes the team right now and applies it to the room itself
	 */
	public function isChangingTeamFromTalk(): bool {
		return $this->changingTeamFromTalk > 0;
	}

	/**
	 * @param ARoomModifiedEvent::PROPERTY_* $property
	 */
	public function applyRoomToTeam(Room $room, string $property): void {
		if ($this->applyingTeamToRoom > 0) {
			// The change comes from the team, writing it back would only echo it
			return;
		}

		$teamId = $room->getObjectId();
		$actorUserId = $this->userSession->getUser()?->getUID();
		if (is_null($actorUserId)) {
			return;
		}
		try {
			switch ($property) {
				case ARoomModifiedEvent::PROPERTY_NAME:
					$this->circlesAdapter->updateTeam($teamId, ['name' => $room->getName()], $actorUserId);
					break;
				case ARoomModifiedEvent::PROPERTY_DESCRIPTION:
					$this->circlesAdapter->updateTeam($teamId, ['description' => $room->getDescription()], $actorUserId);
					break;
				case ARoomModifiedEvent::PROPERTY_LISTABLE:
					$config = $this->circlesAdapter->getTeam($teamId)['config'];
					$newConfig = self::getConfigForListable($config, $room->getListable());
					if ($newConfig !== $config) {
						$this->circlesAdapter->updateTeam($teamId, ['config' => $newConfig], $actorUserId);
					}
					break;
			}
		} catch (\Exception $e) {
			$this->logger->warning('Change of ' . $property . ' in team room ' . $room->getToken() . ' could not be applied to the team', ['exception' => $e]);
		}
	}
}

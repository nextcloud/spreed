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
			&& $participant->getAttendee()->getParticipantType() === Participant::OWNER;
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
				$this->roomService->setDescription($room, $team['description']);
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

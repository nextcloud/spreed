<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Gary Kim <gary@garykim.dev>
 *
 * @author Gary Kim <gary@garykim.dev>
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

namespace OCA\Talk\Federation;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Invitation;
use OCA\Talk\Model\InvitationMapper;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception as DBException;
use OCP\IConfig;
use OCP\IUser;

/**
 * Class FederationManager
 *
 * @package OCA\Talk\Federation
 *
 * FederationManager handles incoming federated rooms
 */
class FederationManager {
	/** @var IConfig */
	private $config;

	/** @var Manager */
	private $manager;

	/** @var ParticipantService */
	private $participantService;

	/** @var InvitationMapper */
	private $invitationMapper;

	public function __construct(
		IConfig $config,
		Manager $manager,
		ParticipantService $participantService,
		InvitationMapper $invitationMapper
	) {
		$this->config = $config;
		$this->manager = $manager;
		$this->participantService = $participantService;
		$this->invitationMapper = $invitationMapper;
	}

	/**
	 * Determine if Talk federation is enabled on this instance
	 * @return bool
	 */
	public function isEnabled(): bool {
		// TODO: Set to default true once implementation is complete
		return $this->config->getAppValue(Application::APP_ID, 'federation_enabled', "false") === "true";
	}

	/**
	 * @param IUser $user
	 * @param int $roomType
	 * @param string $roomName
	 * @param string $roomToken
	 * @param string $remoteUrl
	 * @param string $sharedSecret
	 * @return int share id for this specific remote room share
	 * @throws DBException
	 */
	public function addRemoteRoom(IUser $user, string $remoteId, int $roomType, string $roomName, string $roomToken, string $remoteUrl, string $sharedSecret): int {
		try {
			$room = $this->manager->getRoomByToken($roomToken, null, $remoteUrl);
		} catch (RoomNotFoundException $ex) {
			$room = $this->manager->createRemoteRoom($roomType, $roomName, $roomToken, $remoteUrl);
		}
		$invitation = new Invitation();
		$invitation->setUserId($user->getUID());
		$invitation->setRoomId($room->getId());
		$invitation->setAccessToken($sharedSecret);
		$invitation->setRemoteId($remoteId);
		$invitation = $this->invitationMapper->insert($invitation);

		return $invitation->getId();
	}

	/**
	 * @throws DBException
	 * @throws UnauthorizedException
	 * @throws MultipleObjectsReturnedException
	 */
	public function acceptRemoteRoomShare(IUser $user, int $shareId): void {
		$invitation = $this->invitationMapper->getInvitationById($shareId);
		if ($invitation->getUserId() !== $user->getUID()) {
			throw new UnauthorizedException('invitation is for a different user');
		}

		// Add user to the room
		$room = $this->manager->getRoomById($invitation->getRoomId());
		$participant = [
			[
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'accessToken' => $invitation->getAccessToken(),
				'remoteId' => $invitation->getRemoteId(),
			]
		];
		$this->participantService->addUsers($room, $participant);

		$this->invitationMapper->delete($invitation);

		// TODO: Send SHARE_ACCEPTED notification
	}

	/**
	 * @throws DBException
	 * @throws UnauthorizedException
	 * @throws MultipleObjectsReturnedException
	 */
	public function rejectRemoteRoomShare(IUser $user, int $shareId): void {
		$invitation = $this->invitationMapper->getInvitationById($shareId);
		if ($invitation->getUserId() !== $user->getUID()) {
			throw new UnauthorizedException('invitation is for a different user');
		}
		$this->invitationMapper->delete($invitation);

		// TODO: Send SHARE_DECLINED notification
	}

	/**
	 * @throws DBException
	 */
	public function getNumberOfInvitations(Room $room): int {
		return $this->invitationMapper->countInvitationsForRoom($room);
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use DateTime;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Ban;
use OCA\Talk\Model\BanMapper;
use OCA\Talk\Room;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Security\Ip\IFactory;
use Psr\Log\LoggerInterface;

class BanService {

	public function __construct(
		protected BanMapper $banMapper,
		protected Manager $manager,
		protected ParticipantService $participantService,
		protected IUserManager $userManager,
		protected TalkSession $talkSession,
		protected IRequest $request,
		protected LoggerInterface $logger,
		protected IFactory $ipFactory,
	) {
	}

	/**
	 * Create a new ban
	 *
	 * @throws \InvalidArgumentException
	 */
	public function createBan(Room $room, string $moderatorActorType, string $moderatorActorId, string $moderatorDisplayname, string $bannedActorType, string $bannedActorId, DateTime $bannedTime, string $internalNote): Ban {
		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			throw new \InvalidArgumentException('room');
		}

		if (!in_array($bannedActorType, [Attendee::ACTOR_USERS, Attendee::ACTOR_GUESTS, Attendee::ACTOR_EMAILS, 'ip'], true)) {
			throw new \InvalidArgumentException('bannedActor');
		}

		if (empty($bannedActorId)) {
			throw new \InvalidArgumentException('bannedActor');
		}

		if ($bannedActorType === 'ip') {
			try {
				$this->ipFactory->addressFromString($bannedActorId);
			} catch (\InvalidArgumentException) {
				// Not an IP, check if it's a range
				try {
					$this->ipFactory->rangeFromString($bannedActorId);
				} catch (\InvalidArgumentException) {
					// Not an IP range either
					throw new \InvalidArgumentException('bannedActor');
				}
			}
		}

		if (strlen($internalNote) > Ban::NOTE_MAX_LENGTH) {
			throw new \InvalidArgumentException('internalNote');
		}

		if ($bannedActorType === $moderatorActorType && $bannedActorId === $moderatorActorId) {
			throw new \InvalidArgumentException('self');
		}

		/** @var ?string $displayname */
		$displayname = null;
		if (in_array($bannedActorType, [Attendee::ACTOR_USERS, Attendee::ACTOR_EMAILS, Attendee::ACTOR_GUESTS], true)) {
			try {
				$bannedParticipant = $this->participantService->getParticipantByActor($room, $bannedActorType, $bannedActorId);
				$displayname = $bannedParticipant->getAttendee()->getDisplayName();
				if ($bannedParticipant->hasModeratorPermissions()) {
					throw new \InvalidArgumentException('moderator');
				}
			} catch (ParticipantNotFoundException) {
				// No failure if the banned actor is not in the room yet/anymore
				if ($bannedActorType === Attendee::ACTOR_USERS) {
					$displayname = $this->userManager->getDisplayName($bannedActorId);
				}
			}
		}

		if ($displayname === null || $displayname === '') {
			$displayname = $bannedActorId;
		}

		$ban = new Ban();
		$ban->setModeratorActorType($moderatorActorType);
		$ban->setModeratorActorId($moderatorActorId);
		$ban->setModeratorDisplayname($moderatorDisplayname);
		$ban->setRoomId($room->getId());
		$ban->setBannedActorType($bannedActorType);
		$ban->setBannedActorId($bannedActorId);
		$ban->setBannedDisplayname($displayname);
		$ban->setBannedTime($bannedTime);
		$ban->setInternalNote($internalNote);

		//Remove the banned user from the room
		if ($bannedActorType !== 'ip') {
			try {
				$bannedParticipant = $this->participantService->getParticipantByActor($room, $bannedActorType, $bannedActorId);
				$this->participantService->removeAttendee($room, $bannedParticipant, AAttendeeRemovedEvent::REASON_REMOVED);
			} catch (ParticipantNotFoundException) {
				// No failure if the banned actor is not in the room yet/anymore
			}
		}

		return $this->banMapper->insert($ban);
	}

	public function copyBanForRemoteAddress(Ban $ban, string $remoteAddress): void {
		$this->logger->info('Banned guest detected, banning IP address: ' . $remoteAddress . ' to prevent rejoining.');

		$newBan = new Ban();
		$newBan->setModeratorActorType($ban->getModeratorActorType());
		$newBan->setModeratorActorId($ban->getModeratorActorId());
		$newBan->setModeratorDisplayname($ban->getModeratorDisplayname());
		$newBan->setRoomId($ban->getRoomId());
		$newBan->setBannedTime($ban->getBannedTime());
		$newBan->setInternalNote($ban->getInternalNote());

		$newBan->setBannedActorType('ip');
		$newBan->setBannedActorId($remoteAddress);

		try {
			$this->banMapper->insert($newBan);
		} catch (Exception $e) {
			if ($e->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				return;
			}
			throw $e;
		}
	}

	/**
	 * @throws ForbiddenException
	 */
	public function throwIfActorIsBanned(Room $room, ?string $userId): void {
		if ($userId !== null) {
			$actorType = Attendee::ACTOR_USERS;
			$actorId = $userId;
		} else {
			$actorId = $this->talkSession->getAuthedEmailActorIdForRoom($room->getToken());
			if ($actorId !== null) {
				$actorType = Attendee::ACTOR_EMAILS;
			} else {
				$actorId = $this->talkSession->getGuestActorIdForRoom($room->getToken());
				$actorType = Attendee::ACTOR_GUESTS;
			}
		}

		if ($actorId !== null) {
			try {
				$ban = $this->banMapper->findForBannedActorAndRoom($actorType, $actorId, $room->getId());
				if (in_array($actorType, [Attendee::ACTOR_GUESTS, Attendee::ACTOR_EMAILS], true)) {
					$this->copyBanForRemoteAddress($ban, $this->request->getRemoteAddress());
				}
				throw new ForbiddenException('actor');
			} catch (DoesNotExistException) {
			}
		}

		if ($actorType !== Attendee::ACTOR_GUESTS) {
			return;
		}

		$ipBans = $this->banMapper->findByRoomId($room->getId(), 'ip');

		if (empty($ipBans)) {
			return;
		}

		try {
			$remoteAddress = $this->ipFactory->addressFromString($this->request->getRemoteAddress());
		} catch (\InvalidArgumentException) {
			return;
		}

		foreach ($ipBans as $ban) {
			if ($ban->getBannedActorId() === $this->request->getRemoteAddress()) {
				throw new ForbiddenException('ip');
			}

			try {
				$range = $this->ipFactory->rangeFromString($ban->getBannedActorId());
				if ($range->contains($remoteAddress)) {
					throw new ForbiddenException('ip');
				}
			} catch (\InvalidArgumentException) {
			}
		}
	}

	/**
	 * Check if the actor is banned without logging
	 *
	 * @return bool True if the actor is banned, false otherwise
	 */
	public function isActorBanned(Room $room, string $actorType, string $actorId): bool {
		try {
			$this->banMapper->findForBannedActorAndRoom($actorType, $actorId, $room->getId());
			return true;
		} catch (DoesNotExistException) {
			return false;
		}
	}

	/**
	 * Retrieve all bans for a specific room.
	 *
	 * @return list<Ban>
	 */
	public function getBansForRoom(int $roomId): array {
		return $this->banMapper->findByRoomId($roomId);
	}

	/**
	 * Retrieve all banned userIDs for a specific room.
	 *
	 * @return array<string, mixed> Key is the user ID
	 */
	public function getBannedUserIdsForRoom(int $roomId): array {
		$bans = $this->banMapper->findByRoomId($roomId, Attendee::ACTOR_USERS);
		return array_flip(array_map(static fn (Ban $ban) => $ban->getBannedActorId(), $bans));
	}

	/**
	 * Retrieve all room IDs a user is banned from
	 *
	 * @return array<int, mixed> Key is the room ID
	 */
	public function getBannedRoomsForUserId(string $userId): array {
		$bans = $this->banMapper->findByUserId($userId);
		return array_flip(array_map(static fn (Ban $ban) => $ban->getRoomId(), $bans));
	}

	/**
	 * Retrieve a ban by its ID and delete it.
	 */
	public function findAndDeleteBanByIdForRoom(int $banId, int $roomId): void {
		try {
			$ban = $this->banMapper->findByBanIdAndRoom($banId, $roomId);
			$this->banMapper->delete($ban);
		} catch (DoesNotExistException) {
			// Ban does not exist
		}
	}

	public function updateDisplayNameForActor(string $actorType, string $actorId, string $displayName): void {
		$this->banMapper->updateDisplayNameForActor($actorType, $actorId, $displayName);
	}
}

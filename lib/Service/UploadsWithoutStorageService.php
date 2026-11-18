<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Config;
use OCA\Talk\Exceptions\RoomProperty\UploadsWithoutStorageException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotEnoughSpaceException;
use OCP\Files\NotFoundException;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;

/**
 * Guests, email guests and users with a quota of 0 upload into the Guests
 * folder of the moderator who allowed it.
 */
class UploadsWithoutStorageService {
	public function __construct(
		private readonly Config $talkConfig,
		private readonly ConversationFolderService $conversationFolderService,
		private readonly RoomService $roomService,
		private readonly IRootFolder $rootFolder,
		private readonly IShareManager $shareManager,
		private readonly IUserManager $userManager,
	) {
	}

	public function isAvailable(): bool {
		return $this->talkConfig->isConversationSubfoldersEnabled()
			&& $this->shareManager->shareApiAllowLinks()
			&& $this->shareManager->shareApiLinkAllowPublicUpload();
	}

	public function hasOwnStorage(Participant $participant): bool {
		$attendee = $participant->getAttendee();
		if ($attendee->getActorType() !== Attendee::ACTOR_USERS) {
			return false;
		}

		$user = $this->userManager->get($attendee->getActorId());
		return $user !== null && (int)$user->getQuotaBytes() !== 0;
	}

	/**
	 * @throws UploadsWithoutStorageException
	 */
	public function allowUploads(Participant $moderator, Room $room): void {
		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_TYPE);
		}
		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_BREAKOUT_ROOM);
		}
		if ($room->getObjectType() === Room::OBJECT_TYPE_MATRIX) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_MATRIX);
		}
		if ($room->isFederatedConversation()) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_FEDERATED);
		}
		if (!$this->isAvailable()) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_UNAVAILABLE);
		}
		if (!$this->hasOwnStorage($moderator)) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_STORAGE);
		}

		try {
			$guestsFolder = $this->conversationFolderService->getOrCreateGuestsFolder($moderator->getAttendee()->getActorId(), $room);
		} catch (NotEnoughSpaceException) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_STORAGE);
		}

		$this->roomService->setGuestsFolderId($room, $guestsFolder->getId());
	}

	/**
	 * @throws NotFoundException
	 */
	public function getGuestsFolder(Room $room): Folder {
		return $this->getGuestsFolderById($room->getGuestsFolderId());
	}

	/**
	 * The Guests folder as seen by its owner
	 *
	 * @throws NotFoundException
	 */
	public function getGuestsFolderById(int $folderId): Folder {
		if ($folderId === 0) {
			throw new NotFoundException('Uploads without storage are off');
		}

		$ownerId = $this->rootFolder->getFirstNodeById($folderId)?->getOwner()?->getUID();
		if ($ownerId === null) {
			throw new NotFoundException('Guests folder ' . $folderId . ' not found');
		}

		$folder = $this->rootFolder->getUserFolder($ownerId)->getFirstNodeById($folderId);
		if (!$folder instanceof Folder) {
			throw new NotFoundException('Guests folder ' . $folderId . ' not found');
		}
		return $folder;
	}

	/**
	 * @throws NotFoundException
	 */
	public function getOwnerId(Node $node): string {
		$owner = $node->getOwner();
		if ($owner === null) {
			throw new NotFoundException('Node has no owner');
		}
		return $owner->getUID();
	}

	/**
	 * @return array{owner: string, path: string}
	 * @throws NotFoundException
	 */
	public function getGuestsFolderLocation(Room $room): array {
		$guestsFolder = $this->getGuestsFolder($room);
		$owner = $guestsFolder->getOwner();
		if ($owner === null) {
			throw new NotFoundException('Guests folder has no owner');
		}

		return [
			'owner' => $owner->getDisplayName(),
			'path' => $this->conversationFolderService->getRelativePath($owner->getUID(), $guestsFolder),
		];
	}
}

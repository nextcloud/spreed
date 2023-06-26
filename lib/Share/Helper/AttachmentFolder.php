<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Share\Helper;

use OCA\Talk\Config;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class AttachmentFolder {
	public function __construct(
		protected IRootFolder $rootFolder,
		protected IConfig $serverConfig,
		protected Config $talkConfig,
		protected AttendeeMapper $attendeeMapper,
		protected IManager $shareManager,
		protected LoggerInterface $logger,
	) {
	}

	public function prepareUploadingFile(Room $room, Participant $participant, string $fileName): array {
		$attendee = $participant->getAttendee();
		if ($attendee->getActorType() !== Attendee::ACTOR_USERS) {
			throw new \InvalidArgumentException('participant');
		}

		$userId = $attendee->getActorId();
		$userFolder = $this->rootFolder->getUserFolder($userId);

		$conversationFolder = null;
		$conversationFolderId = 0; // $attendee->getAttachmentFolderId();
		if ($conversationFolderId !== 0) {
			try {
				$conversationFolder = $userFolder->getById($conversationFolderId);
			} catch (NotFoundException $e) {
			}
		}

		$needsClientSharing = false;
		if (!$conversationFolder instanceof Folder) {
			$talkFolder = $this->ensureTalkFolderExists($userFolder, $userId);
			$conversationFolder = $this->createConversationFolderExists($talkFolder, $room, $attendee);
			$needsSharing = $conversationFolder->getId() === $userFolder->getId()
				|| $conversationFolder->getId() === $talkFolder->getId();

			if (!$needsSharing) {
				$share = $this->shareManager->newShare();
				$share->setSharedWith($room->getToken());
				$share->setShareType(IShare::TYPE_ROOM);
				$share->setNode($conversationFolder);
				$share->setShareOwner($attendee->getActorId());
				$share->setSharedBy($attendee->getActorId());
				$share->setPermissions(Constants::PERMISSION_READ | Constants::PERMISSION_SHARE | Constants::PERMISSION_UPDATE);
				$this->shareManager->createShare($share);
			}
		}

		$uniqueFileName = $this->findUniqueName($conversationFolder, $fileName);
		$uploadPath = $conversationFolder->getPath() . '/' . $uniqueFileName;


		return [
			'freeSpace' => $conversationFolder->getFreeSpace(),
			'needsSharing' => $needsClientSharing,
			'uploadPath' => $uploadPath,
		];
	}

	protected function findUniqueName(Folder $conversationFolder, string $fileName): string {
		$pathinfo = pathinfo($fileName);
		$name = $pathinfo['filename'];
		$ext = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';

		$i = 0;
		while (true) {
			$tempName = $i === 0 ? $fileName : $name . ' (' . $i . ')' . $ext;
			try {
				$conversationFolder->get($tempName);
			} catch (NotFoundException $e) {
				return $tempName;
			}
			$i++;
		}
	}

	protected function ensureTalkFolderExists(Folder $userFolder, string $userId): Folder {
		$attachmentFolderName = $this->talkConfig->getAttachmentFolder($userId);
		try {
			try {
				$attachmentFolder = $userFolder->get($attachmentFolderName);
			} catch (NotFoundException $e) {
				$attachmentFolder = $userFolder->newFolder($attachmentFolderName);
			}

			return $attachmentFolder;
		} catch (NotPermittedException $e) {
			$this->serverConfig->setUserValue($userId, 'spreed', 'attachment_folder', '/');
			return $userFolder;
		}
	}

	protected function createConversationFolderExists(Folder $talkFolder, Room $room, Attendee $attendee): Folder {
		try {
			for ($i = 0; $i < 10_000; $i++) {
				$folderName = $i === 0 ? $room->getToken() : $room->getToken() . ' (' . $i . ')';
				try {
					$talkFolder->get($folderName);
				} catch (NotFoundException $e) {
					$folder = $talkFolder->newFolder($folderName);
//					$attendee->setAttachmentFolderId($attachmentFolder->getId());
					$this->attendeeMapper->update($attendee);
					return $folder;
				}
			}

			$this->logger->warning('More than 10k attempts to find a free folder name for conversation ' . $room->getToken() . ' as user ' . $attendee->getActorId() . ' failed, giving up and saving to Talk/ folder.');
		} catch (NotPermittedException $e) {
		}

		return $talkFolder;
	}
}

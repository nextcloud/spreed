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
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class AttachmentFolder {
	public function __construct(
		protected IRootFolder $rootFolder,
		protected IConfig $serverConfig,
		protected Config $talkConfig,
		protected AttendeeMapper $attendeeMapper,
		protected LoggerInterface $logger,
	) {
	}

	protected function prepareSharingFile(Room $room, Participant $participant, string $fileName): array {
		$attendee = $participant->getAttendee();
		if ($attendee->getActorType() !== Attendee::ACTOR_USERS) {
			throw new \InvalidArgumentException('participant');
		}

		$userId = $attendee->getActorId();
		$userFolder = $this->rootFolder->getUserFolder($userId);

		$conversationFolderId = 0; // $attendee->getAttachmentFolderId();
		if ($conversationFolderId !== 0) {
			try {
				$conversationFolder = $userFolder->getById($conversationFolderId);
			} catch (NotFoundException $e) {
			}
		}

		if (!$conversationFolder instanceof Folder) {
			$talkFolder = $this->ensureTalkFolderExists($userFolder, $userId);
			$conversationFolder = $this->createConversationFolderExists($talkFolder, $room, $attendee);
		}

		// FIXME when root or talk folder is used, we need to tell the clients that the individual file still needs sharing


//			$freeSpace = $attachmentFolder->getFreeSpace();
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

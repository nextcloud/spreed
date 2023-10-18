<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Service;

use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\AttachmentMapper;
use OCA\Talk\Room;
use OCA\Talk\Share\RoomShareProvider;
use OCP\Comments\IComment;

class AttachmentService {

	public function __construct(
		protected AttachmentMapper $attachmentMapper,
		protected RoomShareProvider $shareProvider,
	) {
	}

	public function createAttachmentEntry(Room $room, IComment $comment, string $messageType, array $parameters): void {
		$attachment = new Attachment();
		$attachment->setRoomId($room->getId());
		$attachment->setActorType($comment->getActorType());
		$attachment->setActorId($comment->getActorId());
		$attachment->setMessageId((int) $comment->getId());
		$attachment->setMessageTime($comment->getCreationDateTime()->getTimestamp());

		if ($messageType === 'object_shared') {
			$objectType = $parameters['objectType'] ?? '';
			if ($objectType === 'geo-location') {
				$attachment->setObjectType(Attachment::TYPE_LOCATION);
			} elseif ($objectType === 'deck-card') {
				$attachment->setObjectType(Attachment::TYPE_DECK_CARD);
			} elseif ($objectType === 'talk-poll') {
				$attachment->setObjectType(Attachment::TYPE_POLL);
			} else {
				$attachment->setObjectType(Attachment::TYPE_OTHER);
			}
		} else {
			$messageType = $parameters['metaData']['messageType'] ?? '';
			$mimetype = $parameters['metaData']['mimeType'] ?? '';

			if ($messageType === 'record-audio') {
				$attachment->setObjectType(Attachment::TYPE_RECORDING);
			} elseif ($messageType === 'record-video') {
				$attachment->setObjectType(Attachment::TYPE_RECORDING);
			} elseif ($messageType === 'voice-message') {
				$attachment->setObjectType(Attachment::TYPE_VOICE);
			} elseif (str_starts_with($mimetype, 'audio/')) {
				$attachment->setObjectType(Attachment::TYPE_AUDIO);
			} elseif (str_starts_with($mimetype, 'image/') || str_starts_with($mimetype, 'video/')) {
				$attachment->setObjectType(Attachment::TYPE_MEDIA);
			} else {
				$attachment->setObjectType(Attachment::TYPE_FILE);
			}
		}

		$this->attachmentMapper->insert($attachment);
	}

	/**
	 * @param Room $room
	 * @param IComment $comment
	 * @param array{shares?: string[], caption?: string}  $parameters
	 */
	public function createAttachmentEntriesForAllShares(Room $room, IComment $comment, array $parameters): void {

		$shares = $this->shareProvider->getSharesByIds($parameters['shares']);
		foreach ($shares as $share) {
			$mimetype = $share->getNode()->getMimeType();

			$attachment = new Attachment();
			$attachment->setRoomId($room->getId());
			$attachment->setActorType($comment->getActorType());
			$attachment->setActorId($comment->getActorId());
			$attachment->setMessageId((int) $comment->getId());
			$attachment->setMessageTime($comment->getCreationDateTime()->getTimestamp());

			if (str_starts_with($mimetype, 'audio/')) {
				$attachment->setObjectType(Attachment::TYPE_AUDIO);
			} elseif (str_starts_with($mimetype, 'image/') || str_starts_with($mimetype, 'video/')) {
				$attachment->setObjectType(Attachment::TYPE_MEDIA);
			} else {
				$attachment->setObjectType(Attachment::TYPE_FILE);
			}

			$this->attachmentMapper->insert($attachment);
		}
	}

	/**
	 * @param Room $room
	 * @param string $objectType
	 * @param int $offset
	 * @param int $limit
	 * @return Attachment[]
	 */
	public function getAttachmentsByType(Room $room, string $objectType, int $offset, int $limit): array {
		return $this->attachmentMapper->getAttachmentsByType($room->getId(), $objectType, $offset, $limit);
	}

	public function deleteAttachmentByMessageId(int $messageId): void {
		$this->attachmentMapper->deleteByMessageId($messageId);
	}

	public function deleteAttachmentsForRoom(Room $room): void {
		$this->attachmentMapper->deleteByRoomId($room->getId());
	}
}

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\AttachmentMapper;
use OCA\Talk\Room;
use OCP\Comments\IComment;

class AttachmentService {

	public function __construct(
		public AttachmentMapper $attachmentMapper,
	) {
	}

	public function createAttachmentEntry(Room $room, IComment $comment, string $messageType, array $parameters): void {
		$attachment = new Attachment();
		$attachment->setRoomId($room->getId());
		$attachment->setActorType($comment->getActorType());
		$attachment->setActorId($comment->getActorId());
		$attachment->setMessageId((int)$comment->getId());
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

			if ($messageType === ChatManager::VERB_RECORD_AUDIO) {
				$attachment->setObjectType(Attachment::TYPE_RECORDING);
			} elseif ($messageType === ChatManager::VERB_RECORD_VIDEO) {
				$attachment->setObjectType(Attachment::TYPE_RECORDING);
			} elseif ($messageType === ChatManager::VERB_VOICE_MESSAGE) {
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

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Service\AttachmentService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\Comments\NotFoundException;

class UnpinMessage extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		protected Manager $manager,
		protected ChatManager $chatManager,
		protected AttachmentService $attachmentService,
		protected RoomService $roomService,
	) {
		parent::__construct($time);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	protected function run($argument): void {
		$roomId = (int)$argument['roomId'];

		try {
			$room = $this->manager->getRoomById($roomId);
		} catch (RoomNotFoundException) {
			return;
		}

		$messageId = (int)$argument['messageId'];
		try {
			$comment = $this->chatManager->getComment($room, (string)$messageId);
		} catch (NotFoundException) {
			// Message most likely expired, reset the last_pinned_id if matching
			if ($room->getLastPinnedId() === $messageId) {
				$newLastPinned = 0;
				$attachments = $this->attachmentService->getAttachmentsByType($room, Attachment::TYPE_PINNED, 0, 1);
				if (isset($attachments[0])) {
					$newLastPinned = $attachments[0]->getMessageId();
				}
				$this->roomService->setLastPinnedId($room, $newLastPinned);
			}
			return;
		}

		$this->chatManager->unpinMessage($room, $comment, null);
	}
}

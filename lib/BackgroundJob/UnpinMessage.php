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
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\Comments\NotFoundException;

class UnpinMessage extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		protected Manager $manager,
		protected ChatManager $chatManager,
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
			return;
		}

		$this->chatManager->unpinMessage($room, $comment, null);
	}
}

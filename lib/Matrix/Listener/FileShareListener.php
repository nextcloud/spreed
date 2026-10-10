<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Listener;

use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Matrix\Service\SendException;
use OCA\Talk\Matrix\Service\SendService;
use OCA\Talk\Room;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\File;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use Psr\Log\LoggerInterface;

/**
 * Files shared into Matrix conversations are uploaded to the Matrix room
 *
 * @template-implements IEventListener<Event>
 */
class FileShareListener implements IEventListener {
	public function __construct(
		private readonly SendService $sendService,
		private readonly IShareManager $shareManager,
		private readonly LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof SystemMessageSentEvent || $event->getRoom()->getObjectType() !== Room::OBJECT_TYPE_MATRIX) {
			return;
		}

		$comment = $event->getComment();
		$data = json_decode($comment->getMessage(), true);
		if (!is_array($data) || ($data['message'] ?? null) !== 'file_shared' || !isset($data['parameters']['share'])) {
			return;
		}

		try {
			$file = $this->shareManager->getShareById('ocRoomShare:' . $data['parameters']['share'])->getNode();
		} catch (ShareNotFound|\OCP\Files\NotFoundException) {
			return;
		}
		if (!$file instanceof File) {
			return;
		}

		try {
			$caption = $data['parameters']['metaData']['caption'] ?? '';
			$this->sendService->sendFile($event->getRoom(), $comment, $file, is_string($caption) ? $caption : '');
		} catch (SendException $e) {
			$this->logger->warning('Shared file could not be uploaded to the Matrix room of conversation ' . $event->getRoom()->getToken(), ['exception' => $e]);
		}
	}
}

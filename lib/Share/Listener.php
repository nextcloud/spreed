<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Share;

use OC\Files\Filesystem;
use OCA\Talk\Config;
use OCA\Talk\Events\RoomDeletedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Share\Events\BeforeShareCreatedEvent;
use OCP\Share\Events\VerifyMountPointEvent;
use OCP\Share\IShare;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {

	public function __construct(
		protected Config $config,
		protected RoomShareProvider $roomShareProvider,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		match (get_class($event)) {
			BeforeShareCreatedEvent::class => $this->overwriteShareTarget($event),
			VerifyMountPointEvent::class => $this->overwriteMountPoint($event),
			RoomDeletedEvent::class => $this->roomDeletedEvent($event),
		};
	}

	protected function overwriteShareTarget(BeforeShareCreatedEvent $event): void {
		$share = $event->getShare();

		if ($share->getShareType() !== IShare::TYPE_ROOM
			&& $share->getShareType() !== RoomShareProvider::SHARE_TYPE_USERROOM) {
			return;
		}

		// For conversation folder shares the target must include the conversation
		// folder segment (e.g. "/{TALK_PLACEHOLDER}/Group room-abc12345/alice").
		// Extract the path relative to the attachment folder from the node's
		// internal path: "<userId>/files/<attachmentFolder>/<relative...>"
		$relativePath = $share->getNode()->getName();
		$ownerUid = $share->getShareOwner();
		if ($ownerUid !== null) {
			$attachmentFolder = ltrim($this->config->getAttachmentFolder($ownerUid), '/');
			$internalPath = $share->getNode()->getPath();
			$prefix = '/' . $ownerUid . '/files/' . $attachmentFolder . '/';
			if (str_starts_with($internalPath, $prefix)) {
				$relativePath = substr($internalPath, strlen($prefix));
			}
		}

		$target = RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/' . $relativePath;
		$target = Filesystem::normalizePath($target);
		$share->setTarget($target);
	}

	protected function overwriteMountPoint(VerifyMountPointEvent $event): void {
		$share = $event->getShare();

		if ($share->getShareType() !== IShare::TYPE_ROOM
			&& $share->getShareType() !== RoomShareProvider::SHARE_TYPE_USERROOM) {
			return;
		}

		$parent = $event->getParent();
		$placeholder = RoomShareProvider::TALK_FOLDER_PLACEHOLDER;
		if ($parent === $placeholder || str_starts_with($parent, $placeholder . '/')) {
			$attachmentFolder = $this->config->getAttachmentFolder($event->getUser()->getUID());
			$newParent = $attachmentFolder . substr($parent, strlen($placeholder));
			$event->setCreateParent(true);
			$event->setParent($newParent);
		}
	}

	protected function roomDeletedEvent(RoomDeletedEvent $event): void {
		$this->roomShareProvider->deleteInRoom($event->getRoom()->getToken());
	}
}

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

		// For shares of nodes that live inside the user's attachment subfolder
		// hierarchy (e.g. /Talk/<ConvFolder>/<UserSubfolder>) we want the full
		// relative path in the target so that recipients see the correct mount
		// point under their own attachment folder.
		$ownerUid = $share->getShareOwner();
		$relativePath = $share->getNode()->getName();
		if ($ownerUid !== null) {
			$attachmentFolder = ltrim($this->config->getAttachmentFolder($ownerUid), '/');
			$internalPath = $share->getNode()->getPath();
			$prefix = '/' . $ownerUid . '/files/' . $attachmentFolder . '/';
			if (str_starts_with($internalPath, $prefix)) {
				$relativePath = substr($internalPath, strlen($prefix));
			}
		}

		$target = Filesystem::normalizePath(RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/' . $relativePath);
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

		// Handle both the flat case (parent === placeholder) and the nested
		// case (parent starts with placeholder + '/') so that intermediate
		// conversation folders are resolved and created for recipients.
		if ($parent === $placeholder || str_starts_with($parent, $placeholder . '/')) {
			$rest = substr($parent, strlen($placeholder)); // '' or '/<ConvFolder>'
			$resolvedParent = $this->config->getAttachmentFolder($event->getUser()->getUID()) . $rest;
			$event->setCreateParent(true);
			$event->setParent($resolvedParent);
		}
	}

	protected function roomDeletedEvent(RoomDeletedEvent $event): void {
		$this->roomShareProvider->deleteInRoom($event->getRoom()->getToken());
	}
}

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
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
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
		protected Manager $manager,
		protected RoomShareProvider $roomShareProvider,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		match (true) {
			$event instanceof BeforeShareCreatedEvent => $this->overwriteShareTarget($event),
			$event instanceof VerifyMountPointEvent => $this->overwriteMountPoint($event),
			$event instanceof RoomDeletedEvent => $this->roomDeletedEvent($event),
			default => null,
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
		if ($this->config->isConversationSubfoldersEnabled() && $ownerUid !== null) {
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

		if ($parent !== $placeholder && !str_starts_with($parent, $placeholder . '/')) {
			return;
		}

		$uid = $event->getUser()->getUID();
		$attachmentFolder = $this->config->getAttachmentFolder($uid);

		// Flat case: target was stored without a conversation subfolder (legacy shares).
		if ($parent === $placeholder) {
			$event->setCreateParent(true);
			$event->setParent($attachmentFolder);
			return;
		}

		// Nested case: only reached when conversation subfolders are enabled.
		if (!$this->config->isConversationSubfoldersEnabled()) {
			return;
		}

		// Nested case: /{TALK_PLACEHOLDER}/<SharersConvFolder>[/<UserSubfolder>]
		// The conversation folder name was derived from the sharer's perspective.
		// For 1-1 rooms the display name differs per user, so we must recalculate
		// the folder name from the recipient's perspective.
		//
		// The super-share passed to VerifyMountPointEvent by files_sharing only
		// carries id/shareOwner/nodeId/shareType/target — sharedWith is NOT set.
		// Extract the room token from the conv folder name instead
		// (format: "<sanitizedDisplayName>-<token>", token = [a-z0-9]{4,30}).
		$rest = substr($parent, strlen($placeholder) + 1); // 'SharersConvFolder[/UserSubfolder]'
		$segments = explode('/', $rest, 2);               // ['SharersConvFolder', 'UserSubfolder'?]

		$convFolder = $segments[0]; // fallback: keep sharer's name as-is
		if (preg_match('/-([a-z0-9]{4,30})$/', $segments[0], $m)) {
			try {
				$room = $this->manager->getRoomByToken($m[1]);
				$convFolder = $this->config->getConversationFolderName($room, $uid);
			} catch (RoomNotFoundException) {
				// Room gone — keep the sharer's folder name as a fallback.
			}
		}

		$resolvedParent = $attachmentFolder . '/' . $convFolder;
		if (isset($segments[1]) && $segments[1] !== '') {
			$resolvedParent .= '/' . $segments[1];
		}

		$event->setCreateParent(true);
		$event->setParent($resolvedParent);
	}

	protected function roomDeletedEvent(RoomDeletedEvent $event): void {
		$this->roomShareProvider->deleteInRoom($event->getRoom()->getToken());
	}
}

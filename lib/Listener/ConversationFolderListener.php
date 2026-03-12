<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Config as TalkConfig;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Room;
use OCA\Talk\Share\RoomShareProvider;
use OCP\Constants;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Handles two events for the lazy per-conversation attachment folder feature:
 *
 * 1. NodeCreatedEvent — when the frontend does a WebDAV MKCOL to create the
 *    user's conversation subfolder, this listener detects it, validates that it
 *    belongs to a room the user is a member of, and automatically creates a
 *    TYPE_ROOM share on the subfolder so all room members can access it.
 *    Creation is idempotent: if the share already exists it is skipped.
 *
 * 2. RoomModifiedEvent (name change) — renames existing conversation folders
 *    for all user participants and updates the share targets in the database.
 *
 * @template-implements IEventListener<Event>
 */
class ConversationFolderListener implements IEventListener {
	public function __construct(
		private TalkConfig $talkConfig,
		private Manager $manager,
		private AttendeeMapper $attendeeMapper,
		private IShareManager $shareManager,
		private RoomShareProvider $roomShareProvider,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof NodeCreatedEvent) {
			$node = $event->getNode();
			if ($node instanceof Folder) {
				$this->processCreatedFolder($node);
			}
		} elseif ($event instanceof RoomModifiedEvent) {
			try {
				$this->handleRoomModified($event);
			} catch (\Exception $e) {
				$this->logger->error('ConversationFolderListener: room rename failed: {error}', [
					'error' => $e->getMessage(),
					'exception' => $e,
				]);
			}
		}
	}

	/**
	 * When a room is renamed, rename the conversation folders for all user
	 * participants and update the stored share targets.
	 */
	private function handleRoomModified(RoomModifiedEvent $event): void {
		if ($event->getProperty() !== ARoomModifiedEvent::PROPERTY_NAME) {
			return;
		}

		$room = $event->getRoom();
		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			return;
		}

		$oldName = (string)$event->getOldValue();
		$newName = (string)$event->getNewValue();
		$token = $room->getToken();
		$oldFolderName = $this->talkConfig->buildConversationFolderName($oldName, $token);
		$newFolderName = $this->talkConfig->buildConversationFolderName($newName, $token);

		if ($oldFolderName === $newFolderName) {
			return;
		}

		$attendees = $this->attendeeMapper->getActorsByType($room->getId(), Attendee::ACTOR_USERS);
		foreach ($attendees as $attendee) {
			$this->renameConversationFolder($attendee->getActorId(), $oldFolderName, $newFolderName);
		}

		$this->roomShareProvider->updateShareTargetsInRoom($token, $oldFolderName, $newFolderName);
	}

	private function renameConversationFolder(string $userId, string $oldFolderName, string $newFolderName): void {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
		} catch (\Exception $e) {
			$this->logger->debug('ConversationFolderListener: cannot get user folder for {userId}: {error}', [
				'userId' => $userId,
				'error' => $e->getMessage(),
			]);
			return;
		}

		$attachmentFolder = ltrim($this->talkConfig->getAttachmentFolder($userId), '/');

		try {
			$attachmentNode = $userFolder->get($attachmentFolder);
			if (!($attachmentNode instanceof Folder)) {
				return;
			}
			$oldFolder = $attachmentNode->get($oldFolderName);
			if (!($oldFolder instanceof Folder)) {
				return;
			}
			$oldFolder->move($attachmentNode->getPath() . '/' . $newFolderName);
		} catch (NotFoundException) {
			// Old folder does not exist — nothing to rename.
		} catch (\Exception $e) {
			$this->logger->debug('ConversationFolderListener: cannot rename folder for {userId}: {error}', [
				'userId' => $userId,
				'error' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Called when a Folder node is created via WebDAV MKCOL.
	 *
	 * Expected internal path: /<ownerUid>/files/<attachmentFolder>/<convFolder>/<userSubfolder>
	 * — exactly two segments after the attachment folder prefix.
	 *
	 * Security checks:
	 *  - The conversation folder name must match what Talk would generate for
	 *    an actual room the user is a member of (prevents sharing arbitrary folders).
	 *  - The subfolder name must match the expected name for the owner uid.
	 */
	private function processCreatedFolder(Folder $folder): void {
		$path = $folder->getPath();

		// Extract ownerUid from the first path segment: /<ownerUid>/…
		$firstSlash = strpos($path, '/', 1);
		if ($firstSlash === false) {
			return;
		}
		$ownerUid = substr($path, 1, $firstSlash - 1);

		// Build expected prefix and reject anything outside it.
		// getAttachmentFolder() returns e.g. "/Talk" (with leading slash).
		$attachmentFolder = $this->talkConfig->getAttachmentFolder($ownerUid);
		$expectedPrefix = '/' . $ownerUid . '/files' . $attachmentFolder . '/';

		if (!str_starts_with($path, $expectedPrefix)) {
			return;
		}

		// The suffix after the prefix must be exactly "<convFolder>/<userSubfolder>"
		// — one slash, nothing further.
		$suffix = substr($path, strlen($expectedPrefix));
		$slashPos = strpos($suffix, '/');
		if ($slashPos === false || strpos($suffix, '/', $slashPos + 1) !== false) {
			return;
		}

		$convFolderName = substr($suffix, 0, $slashPos);
		$subFolderName = substr($suffix, $slashPos + 1);

		// Subfolder name must be "<something>-<uid>" or just "<uid>".
		if ($subFolderName !== $ownerUid && !str_ends_with($subFolderName, '-' . $ownerUid)) {
			return;
		}

		// Extract token: everything after the last '-' in the conversation folder name.
		$lastDash = strrpos($convFolderName, '-');
		if ($lastDash === false) {
			return;
		}
		$token = substr($convFolderName, $lastDash + 1);

		// Verify the room exists and the user is a participant.
		try {
			$room = $this->manager->getRoomForUserByToken($token, $ownerUid);
		} catch (RoomNotFoundException) {
			return;
		}

		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			return;
		}

		// Verify the conversation folder name matches what Talk would generate.
		// Use getName() directly — getDisplayName() may return an empty string in
		// listener context before the user filesystem is fully set up.
		$expectedConvFolderName = $this->talkConfig->buildConversationFolderName($room->getName(), $token);
		if ($convFolderName !== $expectedConvFolderName) {
			return;
		}

		// Idempotency: skip if the subfolder is already shared with this room.
		$existing = $this->shareManager->getSharesBy($ownerUid, IShare::TYPE_ROOM, $folder, false, 1);
		if (!empty($existing)) {
			return;
		}

		$share = $this->shareManager->newShare();
		$share->setNode($folder)
			->setShareType(IShare::TYPE_ROOM)
			->setSharedBy($ownerUid)
			->setShareOwner($ownerUid)
			->setSharedWith($token)
			->setPermissions(Constants::PERMISSION_READ)
			->setMailSend(false);

		$this->shareManager->createShare($share);
	}
}

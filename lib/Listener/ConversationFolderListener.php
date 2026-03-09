<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Config as TalkConfig;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
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
 * Handles three events:
 *
 * 1. AttendeesAddedEvent — proactively creates the per-user conversation
 *    folder hierarchy when a user joins or is added to a room.
 *
 * 2. RoomModifiedEvent (name change) — renames existing conversation folders
 *    for all user participants and updates share targets in the database.
 *
 * 3. NodeCreatedEvent — auto-shares the innermost subfolder with the room
 *    when it is created (either proactively by this listener or later by
 *    the frontend via WebDAV MKCOL).
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
		if ($event instanceof AttendeesAddedEvent) {
			$this->handleAttendeesAdded($event);
		} elseif ($event instanceof RoomModifiedEvent) {
			$this->handleRoomModified($event);
		} elseif ($event instanceof NodeCreatedEvent) {
			$node = $event->getNode();
			if ($node instanceof Folder) {
				$this->processCreatedFolder($node);
			}
		}
	}

	/**
	 * When users are added to a group or public room, create their
	 * per-conversation upload folder. The NodeCreatedEvent fired by
	 * the folder creation will trigger processCreatedFolder() which
	 * creates the TYPE_ROOM share.
	 */
	private function handleAttendeesAdded(AttendeesAddedEvent $event): void {
		$room = $event->getRoom();

		// Only create folders for group and public rooms
		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			return;
		}

		foreach ($event->getAttendees() as $attendee) {
			if ($attendee->getActorType() !== Attendee::ACTOR_USERS) {
				continue;
			}

			$userId = $attendee->getActorId();
			$this->ensureConversationFolder($userId, $room);
		}
	}

	/**
	 * When a room is renamed, rename the existing conversation folders
	 * for all user participants.
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

	/**
	 * Rename a user's conversation folder from old to new name.
	 */
	private function renameConversationFolder(string $userId, string $oldFolderName, string $newFolderName): void {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
		} catch (\Exception $e) {
			$this->logger->debug('Could not get user folder for {userId}: {error}', [
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
			// Old folder doesn't exist — nothing to rename
		} catch (\Exception $e) {
			$this->logger->debug('Could not rename conversation folder for {userId}: {error}', [
				'userId' => $userId,
				'error' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Create the folder hierarchy <attachmentFolder>/<convFolderName>/<subfolderName>
	 * for a user in a room. Silently ignores errors (folder may already exist,
	 * or the user's storage may not be available yet).
	 */
	private function ensureConversationFolder(string $userId, Room $room): void {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
		} catch (\Exception $e) {
			$this->logger->debug('Could not get user folder for {userId}: {error}', [
				'userId' => $userId,
				'error' => $e->getMessage(),
			]);
			return;
		}

		$attachmentFolder = ltrim($this->talkConfig->getAttachmentFolder($userId), '/');
		$convFolderName = $this->talkConfig->getConversationFolderName($room, $userId);
		$subfolderName = $this->talkConfig->getConversationSubfolderName($userId);

		$segments = [$attachmentFolder, $convFolderName, $subfolderName];
		$current = $userFolder;
		foreach ($segments as $segment) {
			try {
				$node = $current->get($segment);
				if (!($node instanceof Folder)) {
					// A file exists where we need a folder — can't proceed
					return;
				}
				$current = $node;
			} catch (NotFoundException) {
				try {
					$current = $current->newFolder($segment);
				} catch (\Exception $e) {
					$this->logger->debug('Could not create folder {segment} for {userId}: {error}', [
						'segment' => $segment,
						'userId' => $current->getPath() . '/' . $segment,
						'error' => $e->getMessage(),
					]);
					return;
				}
			}
		}
	}

	private function processCreatedFolder(Folder $folder): void {
		// Internal path format: /<userId>/files/<attachmentFolder>/<convName>-<token>/<userId>
		$path = $folder->getPath();

		// Extract the owner uid from the first path segment
		$firstSlash = strpos($path, '/', 1);
		if ($firstSlash === false) {
			return;
		}
		$ownerUid = substr($path, 1, $firstSlash - 1);

		// Build the expected path prefix and reject anything that doesn't start with it.
		// $attachmentFolder already has a leading slash, e.g. "/Talk".
		$attachmentFolder = $this->talkConfig->getAttachmentFolder($ownerUid);
		$expectedPrefix = '/' . $ownerUid . '/files' . $attachmentFolder . '/';
		if (!str_starts_with($path, $expectedPrefix)) {
			return;
		}

		// The suffix after the prefix must be exactly "<convFolder>/<userId>" with no
		// further slashes (i.e. exactly two segments).
		$suffix = substr($path, strlen($expectedPrefix));
		$slashPos = strpos($suffix, '/');
		if ($slashPos === false || strpos($suffix, '/', $slashPos + 1) !== false) {
			return;
		}
		$convFolderName = substr($suffix, 0, $slashPos);
		$subfolderName = substr($suffix, $slashPos + 1);

		// The subfolder name must match the expected "<displayNamePrefix>-<uid>" (or just "<uid>")
		if ($subfolderName !== $this->talkConfig->getConversationSubfolderName($ownerUid)) {
			return;
		}

		// Extract token: last segment after the last '-' in the conversation folder name
		$lastDash = strrpos($convFolderName, '-');
		if ($lastDash === false) {
			return;
		}
		$token = substr($convFolderName, $lastDash + 1);

		try {
			$room = $this->manager->getRoomForUserByToken($token, $ownerUid);
		} catch (RoomNotFoundException) {
			return;
		}

		// Verify the folder name matches exactly what Talk would generate
		if ($convFolderName !== $this->talkConfig->getConversationFolderName($room, $ownerUid)) {
			return;
		}

		// Idempotency: skip if already shared
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

		try {
			$this->shareManager->createShare($share);
		} catch (\Exception) {
			// Already shared or share creation failed — ignore
		}
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Share;

use OC\Files\Filesystem;
use OCA\Files_Sharing\Event\ShareMountedEvent;
use OCA\Talk\Config;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ConversationFolderService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\Mount\IMountManager;
use OCP\IUser;
use OCP\Share\Events\BeforeShareCreatedEvent;
use OCP\Share\Events\VerifyMountPointEvent;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {
	/**
	 * Node ids by resolved target per user, which are not in the mount cache yet during the setup
	 * @var array<string, array<string, int>>
	 */
	private array $resolvedTargets = [];

	public function __construct(
		private readonly Config $config,
		private readonly Manager $manager,
		private readonly RoomShareProvider $roomShareProvider,
		private readonly IMountManager $mountManager,
		private readonly IMountProviderCollection $mountProviderCollection,
		private readonly IUserMountCache $userMountCache,
		private readonly LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		match (true) {
			$event instanceof BeforeShareCreatedEvent => $this->overwriteShareTarget($event),
			$event instanceof VerifyMountPointEvent => $this->overwriteMountPoint($event),
			$event instanceof ShareMountedEvent => $this->resolveMountPoint($event),
			$event instanceof RoomDeletedEvent => $this->roomDeletedEvent($event),
			$event instanceof AttendeesRemovedEvent => $this->roomAttendeesRemovedEvent($event),
			$event instanceof RoomModifiedEvent => $this->roomModifiedEvent($event),
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
		if ($share->getShareType() === IShare::TYPE_ROOM && $this->config->isConversationSubfoldersEnabled() && $ownerUid !== null) {
			$attachmentFolder = ltrim($this->config->getAttachmentFolder($ownerUid), '/');
			$internalPath = $share->getNode()->getPath();
			$prefix = '/' . $ownerUid . '/files/' . $attachmentFolder . '/';
			if (str_starts_with($internalPath, $prefix)) {
				$candidate = substr($internalPath, strlen($prefix));
				// Only keep the full relative path when the file sits inside a
				// user subfolder (first segment is "<name>-<userid>") inside the
				// conversation subfolder (first segment is "<name>-<token>").
				// Other subdirectories (e.g. Recording/<token>/) are not part of
				// the conversation subfolder hierarchy and must fall back to the
				// flat filename so that recipients see the file at Talk/<filename>.
				$segments = explode('/', $candidate, 3);
				$potentialConversationFolder = $segments[0];
				$potentialUserFolder = $segments[1] ?? '';
				if (str_ends_with($potentialConversationFolder, '-' . $share->getSharedWith())
					&& (str_ends_with($potentialUserFolder, '-' . $share->getShareOwner())
						|| str_ends_with($potentialUserFolder, '-' . $share->getShareOwner() . ConversationFolderService::UPDATABLE_SUFFIX))) {
					$relativePath = $candidate;
				}
			}
		}

		$target = Filesystem::normalizePath(RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/' . $relativePath);
		$share->setTarget($target);
	}

	/**
	 * Shares without userroom share keep the placeholder for the mount point validation,
	 * so resolve it on mount once and store the target in a userroom share.
	 */
	protected function resolveMountPoint(ShareMountedEvent $event): void {
		$mount = $event->getMount();
		$share = $mount->getShare();
		if ($share->getShareType() !== IShare::TYPE_ROOM
			|| !str_starts_with($share->getTarget(), RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/')) {
			return;
		}

		$user = $mount->getUser();
		$uid = $user->getUID();
		try {
			// Unlike overwriteMountPoint(), nested targets would keep the conversation folder name of the sharer,
			// which differs in one-to-one conversations. This does not happen, as shares of conversation folders
			// always get a userroom share on creation.
			$attachmentFolder = $this->config->getAttachmentFolder($uid);
			$target = str_replace(RoomShareProvider::TALK_FOLDER_PLACEHOLDER, $attachmentFolder, $share->getTarget());
			// The root folder as attachment folder results in "//file.txt"
			$target = Filesystem::normalizePath($target);
			$target = $this->getUniqueTarget($user, $target, $share->getNodeId());
			// The share storage uses the target of the share as its mount point
			$share->setTarget($target);
			$mount->setMountPoint('/' . $uid . '/files' . $target . '/');

			// Not IManager::moveShare(), which would update the share mounts again
			foreach ($mount->getGroupedShares() as $groupedShare) {
				if ($groupedShare->getShareType() === IShare::TYPE_ROOM
					&& str_starts_with($groupedShare->getTarget(), RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/')) {
					$groupedShare->setTarget($target);
					$this->roomShareProvider->move($groupedShare, $uid);
				}
			}
		} catch (\Throwable $e) {
			// files_sharing skips the whole share mount on exceptions
			$this->logger->warning('Could not resolve the mount point of room share ' . $share->getId(), ['exception' => $e]);
		}
	}

	/**
	 * Like ShareTargetValidator::generateUniqueTarget() of files_sharing, without setting up the file system.
	 * Any cached mount of another node is a conflict, regardless of its mount provider.
	 */
	protected function getUniqueTarget(IUser $user, string $target, int $nodeId): string {
		// The home mount is not set up yet when the share mounts of another user are updated
		$homeMount = $this->mountManager->getAll()['/' . $user->getUID() . '/'] ?? $this->mountProviderCollection->getHomeMountForUser($user);
		$cache = $homeMount->getStorage()->getCache();
		$pathInfo = pathinfo($target);
		$extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

		$uniqueTarget = $target;
		for ($i = 2; ; $i++) {
			$mount = $this->userMountCache->getMountAtPath($user, '/' . $user->getUID() . '/files' . $uniqueTarget . '/');
			if (!$cache->inCache('files' . $uniqueTarget)
				&& ($mount === null || $mount->getRootId() === $nodeId)
				&& ($this->resolvedTargets[$user->getUID()][$uniqueTarget] ?? $nodeId) === $nodeId) {
				$this->resolvedTargets[$user->getUID()][$uniqueTarget] = $nodeId;
				return $uniqueTarget;
			}
			$uniqueTarget = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . ' (' . $i . ')' . $extension;
			$uniqueTarget = Filesystem::normalizePath($uniqueTarget);
		}
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

	protected function roomModifiedEvent(RoomModifiedEvent $event): void {
		if ($event->getProperty() !== ARoomModifiedEvent::PROPERTY_PASSWORD) {
			return;
		}

		$room = $event->getRoom();
		$this->roomShareProvider->setPasswordInRoom($room->getToken(), $room->getPassword());
	}

	protected function roomAttendeesRemovedEvent(AttendeesRemovedEvent $event): void {
		$userIds = array_values(array_map(
			static fn (Attendee $attendee): string => $attendee->getActorId(),
			array_filter(
				$event->getAttendees(),
				static fn (Attendee $attendee): bool => $attendee->getActorType() === Attendee::ACTOR_USERS
			)
		));

		$this->roomShareProvider->deleteReceivedSharesInRoom($event->getRoom()->getToken(), $userIds);
	}
}

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
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Model\Attendee;
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
		protected Config $config,
		protected RoomShareProvider $roomShareProvider,
		protected IMountManager $mountManager,
		protected IMountProviderCollection $mountProviderCollection,
		protected IUserMountCache $userMountCache,
		protected LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		match (get_class($event)) {
			BeforeShareCreatedEvent::class => $this->overwriteShareTarget($event),
			VerifyMountPointEvent::class => $this->overwriteMountPoint($event),
			ShareMountedEvent::class => $this->resolveMountPoint($event),
			RoomDeletedEvent::class => $this->roomDeletedEvent($event),
			AttendeesRemovedEvent::class => $this->roomAttendeesRemovedEvent($event),
		};
	}

	protected function overwriteShareTarget(BeforeShareCreatedEvent $event): void {
		$share = $event->getShare();

		if ($share->getShareType() !== IShare::TYPE_ROOM
			&& $share->getShareType() !== RoomShareProvider::SHARE_TYPE_USERROOM) {
			return;
		}

		$target = RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/' . $share->getNode()->getName();
		$target = Filesystem::normalizePath($target);
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

		if ($event->getParent() === RoomShareProvider::TALK_FOLDER_PLACEHOLDER) {
			if (method_exists($event, 'getUser')) {
				$userId = $event->getUser()->getUID();
			} else {
				$view = $event->getView();
				// If we fail to get the owner of the view from the cache,
				// e.g. because the user never logged in but a cron job runs
				// We fall back to calculating the owner from the root of the view:
				if (substr_count($view->getRoot(), '/') >= 2) {
					// /37c09aa0-1b92-4cf6-8c66-86d8cac8c1d0/files
					[, $userId, ] = explode('/', $view->getRoot(), 3);
				} else {
					// Something weird is going on, we can't fall back more
					// so for now we don't overwrite the share path ¯\_(ツ)_/¯
					return;
				}
			}

			$parent = $this->config->getAttachmentFolder($userId);
			$event->setParent($parent);
			if (method_exists($event, 'setCreateParent')) {
				$event->setCreateParent(true);
			} else {
				if (!$event->getView()->is_dir($parent)) {
					$event->getView()->mkdir($parent);
				}
			}
		}
	}

	protected function roomDeletedEvent(RoomDeletedEvent $event): void {
		$this->roomShareProvider->deleteInRoom($event->getRoom()->getToken());
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

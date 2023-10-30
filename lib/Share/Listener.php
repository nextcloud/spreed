<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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

		$target = RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/' . $share->getNode()->getName();
		$target = Filesystem::normalizePath($target);
		$share->setTarget($target);
	}

	protected function overwriteMountPoint(VerifyMountPointEvent $event): void {
		$share = $event->getShare();
		$view = $event->getView();

		if ($share->getShareType() !== IShare::TYPE_ROOM
			&& $share->getShareType() !== RoomShareProvider::SHARE_TYPE_USERROOM) {
			return;
		}

		if ($event->getParent() === RoomShareProvider::TALK_FOLDER_PLACEHOLDER) {
			try {
				$userId = $view->getOwner('/');
			} catch (\Exception $e) {
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
			if (!$event->getView()->is_dir($parent)) {
				$event->getView()->mkdir($parent);
			}
		}
	}

	protected function roomDeletedEvent(RoomDeletedEvent $event): void {
		$this->roomShareProvider->deleteInRoom($event->getRoom()->getToken());
	}
}

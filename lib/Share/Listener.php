<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Share\Events\VerifyMountPointEvent;
use OCP\Share\IShare;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {
	public static function register(IEventDispatcher $dispatcher): void {
		/**
		 * @psalm-suppress UndefinedClass
		 */
		$dispatcher->addListener('OCP\Share::preShare', [self::class, 'listenPreShare'], 1000);
		$dispatcher->addListener(VerifyMountPointEvent::class, [self::class, 'listenVerifyMountPointEvent'], 1000);
	}

	public static function listenPreShare(GenericEvent $event): void {
		/** @var self $listener */
		$listener = \OC::$server->query(self::class);
		$listener->overwriteShareTarget($event);
	}

	public static function listenVerifyMountPointEvent(VerifyMountPointEvent $event): void {
		/** @var self $listener */
		$listener = \OC::$server->query(self::class);
		$listener->overwriteMountPoint($event);
	}

	/** @var Config */
	protected $config;

	public function __construct(Config $config) {
		$this->config = $config;
	}

	public function overwriteShareTarget(GenericEvent $event): void {
		/** @var IShare $share */
		$share = $event->getSubject();

		if ($share->getShareType() !== IShare::TYPE_ROOM
			&& $share->getShareType() !== RoomShareProvider::SHARE_TYPE_USERROOM) {
			return;
		}

		$target = RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/' . $share->getNode()->getName();
		$target = Filesystem::normalizePath($target);
		$share->setTarget($target);
	}

	public function overwriteMountPoint(VerifyMountPointEvent $event): void {
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
				// We fallback to calculating the owner from the root of the view:
				if (substr_count($view->getRoot(), '/') >= 2) {
					// /37c09aa0-1b92-4cf6-8c66-86d8cac8c1d0/files
					[, $userId, ] = explode('/', $view->getRoot(), 3);
				} else {
					// Something weird is going on, we can't fallback more
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
}

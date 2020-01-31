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
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Share\Events\VerifyMountPointEvent;
use OCP\Share\IShare;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {

	public static function register(IEventDispatcher $dispatcher): void {
		$dispatcher->addListener('OCP\Share::preShare', [self::class, 'overwriteShareTarget'], 1000);
		$dispatcher->addListener(VerifyMountPointEvent::class, [self::class, 'overwriteMountPoint'], 1000);
	}

	public static function overwriteShareTarget(GenericEvent $event): void {
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

	public static function overwriteMountPoint(VerifyMountPointEvent $event): void {
		$share = $event->getShare();

		if ($share->getShareType() !== IShare::TYPE_ROOM
			&& $share->getShareType() !== RoomShareProvider::SHARE_TYPE_USERROOM) {
			return;
		}

		if ($event->getParent() === RoomShareProvider::TALK_FOLDER_PLACEHOLDER) {
			$parent = RoomShareProvider::TALK_FOLDER; // FIXME user preference
			$event->setParent($parent);
			if (!$event->getView()->is_dir($parent)) {
				$event->getView()->mkdir($parent);
			}
		}
	}
}

<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Collaboration\Resources;

use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCP\Collaboration\Resources\IManager;
use OCP\Collaboration\Resources\ResourceException;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {
	public static function register(EventDispatcherInterface $dispatcher): void {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			/** @var IManager $manager */
			$resourceManager = \OC::$server->query(IManager::class);

			try {
				$resource = $resourceManager->getResourceForUser('room', $room->getToken(), null);
			} catch (ResourceException $e) {
				return;
			}
			$resourceManager->invalidateAccessCacheForResource($resource);
		};
		$dispatcher->addListener(Room::class . '::postDeleteRoom', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			/** @var IManager $manager */
			$resourceManager = \OC::$server->query(IManager::class);
			/** @var IUserManager $userManager */
			$userManager = \OC::$server->getUserManager();
			try {
				$resource = $resourceManager->getResourceForUser('room', $room->getToken(), null);
			} catch (ResourceException $e) {
				return;
			}

			$participants = $event->getArgument('users');
			foreach ($participants as $participant) {
				$user = null;
				if ($participant['user_id'] !== '') {
					$user = $userManager->get($participant['user_id']);
				}

				$resourceManager->invalidateAccessCacheForResourceByUser($resource, $user);
			}
		};
		$dispatcher->addListener(Room::class . '::postAddUser', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			/** @var IManager $manager */
			$resourceManager = \OC::$server->query(IManager::class);
			/** @var IUser $user */
			$user = $event->getArgument('user');
			try {
				$resource = $resourceManager->getResourceForUser('room', $room->getToken(), null);
			} catch (ResourceException $e) {
				return;
			}

			$resourceManager->invalidateAccessCacheForResourceByUser($resource, $user);
		};
		$dispatcher->addListener(Room::class . '::postRemoveUser', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			/** @var IManager $manager */
			$resourceManager = \OC::$server->query(IManager::class);
			/** @var IUserManager $userManager */
			$userManager = \OC::$server->getUserManager();
			try {
				$resource = $resourceManager->getResourceForUser('room', $room->getToken(), null);
			} catch (ResourceException $e) {
				return;
			}

			/** @var Participant $participant */
			$participant = $event->getArgument('participant');
			$user = $userManager->get($participant->getUser());
			$resourceManager->invalidateAccessCacheForResourceByUser($resource, $user);
		};
		$dispatcher->addListener(Room::class . '::postRemoveBySession', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			/** @var IManager $manager */
			$resourceManager = \OC::$server->query(IManager::class);

			try {
				$resource = $resourceManager->getResourceForUser('room', $room->getToken(), null);
			} catch (ResourceException $e) {
				return;
			}
			$resourceManager->invalidateAccessCacheForResourceByUser($resource, null);
		};
		$dispatcher->addListener(Room::class . '::postChangeType', $listener);
		$dispatcher->addListener(Room::class . '::postInviteByEmail', $listener);
	}
}

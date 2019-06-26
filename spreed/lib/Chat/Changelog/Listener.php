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

namespace OCA\Spreed\Chat\Changelog;

use OCA\Spreed\Controller\RoomController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {

	public static function register(EventDispatcherInterface $dispatcher): void {
		$dispatcher->addListener(RoomController::class . '::preGetRooms', function(GenericEvent $event) {
			$userId = $event->getArgument('userId');

			/** @var Listener $listener */
			$listener = \OC::$server->query(self::class);
			$listener->preGetRooms($userId);
		}, -100);
	}

	/** @var Manager */
	protected $manager;

	public function __construct(Manager $manager) {
		$this->manager = $manager;
	}

	public function preGetRooms(string $userId): void {
		if (!$this->manager->userHasNewChangelog($userId)) {
			return;
		}

		$this->manager->updateChangelog($userId);
	}
}

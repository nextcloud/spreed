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

namespace OCA\Spreed\Listener;


use OCA\Spreed\Exceptions\ForbiddenException;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCP\IConfig;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class RestrictStartingCalls {

	/** @var IConfig */
	protected $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	public static function register(EventDispatcherInterface $dispatcher): void {
		$dispatcher->addListener(Room::class . '::preSessionJoinCall', function(GenericEvent $event) {

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			$listener->checkStartCallPermissions($event);
		}, 1000);
	}

	/**
	 * @param GenericEvent $event
	 * @throws ForbiddenException
	 */
	public function checkStartCallPermissions(GenericEvent $event): void {
		/** @var Room $room */
		$room = $event->getSubject();
		/** @var string $sessionId */
		$sessionId = $event->getArgument('sessionId');
		/** @var Participant $participant */
		$participant = $room->getParticipantBySession($sessionId);

		if (!$participant->canStartCall() && !$room->hasSessionsInCall()) {
			throw new ForbiddenException('Can not start a call');
		}
	}
}

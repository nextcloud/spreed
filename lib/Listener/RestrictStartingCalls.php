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

namespace OCA\Talk\Listener;

use OCA\Talk\Events\ModifyParticipantEvent;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;

class RestrictStartingCalls {

	/** @var IConfig */
	protected $config;

	/** @var ParticipantService */
	protected $participantService;

	public function __construct(IConfig $config,
								ParticipantService $participantService) {
		$this->config = $config;
		$this->participantService = $participantService;
	}

	public static function register(IEventDispatcher $dispatcher): void {
		$dispatcher->addListener(Room::EVENT_BEFORE_SESSION_JOIN_CALL, static function (ModifyParticipantEvent $event) {
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			$listener->checkStartCallPermissions($event);
		}, 1000);
	}

	/**
	 * @param ModifyParticipantEvent $event
	 * @throws ForbiddenException
	 */
	public function checkStartCallPermissions(ModifyParticipantEvent $event): void {
		$room = $event->getRoom();
		$participant = $event->getParticipant();

		if (!$participant->canStartCall($this->config) && !$this->participantService->hasActiveSessionsInCall($room)) {
			throw new ForbiddenException('Can not start a call');
		}
	}
}

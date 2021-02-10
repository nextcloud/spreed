<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Joas Schilling <coding@schilljs.com>
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

use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ParticipantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserChangedEvent;

class UserDisplayNameListener implements IEventListener {

	/** @var ParticipantService */
	private $participantService;

	public function __construct(ParticipantService $participantService) {
		$this->participantService = $participantService;
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserChangedEvent)) {
			// Unrelated
			return;
		}

		if ($event->getFeature() !== 'displayName') {
			// Unrelated
			return;
		}

		$this->participantService->updateDisplayNameForActor(
			Attendee::ACTOR_USERS,
			$event->getUser()->getUID(),
			(string) $event->getValue()
		);
	}
}

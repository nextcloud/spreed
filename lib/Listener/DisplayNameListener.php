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
use OCA\Talk\Service\PollService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupChangedEvent;
use OCP\User\Events\UserChangedEvent;

/**
 * @template-implements IEventListener<Event>
 */
class DisplayNameListener implements IEventListener {

	public function __construct(
		private ParticipantService $participantService,
		private PollService $pollService,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserChangedEvent && $event->getFeature() === 'displayName') {
			$this->updateCachedName(Attendee::ACTOR_USERS, $event->getUser()->getUID(), (string) $event->getValue());
		}
		if ($event instanceof GroupChangedEvent && $event->getFeature() === 'displayName') {
			$this->updateCachedName(Attendee::ACTOR_GROUPS, $event->getGroup()->getGID(), (string) $event->getValue());
		}
	}

	/**
	 * @psalm-param Attendee::ACTOR_* $actorType
	 */
	protected function updateCachedName(string $actorType, string $actorId, string $newName): void {
		$this->participantService->updateDisplayNameForActor(
			$actorType,
			$actorId,
			$newName
		);

		$this->pollService->updateDisplayNameForActor(
			$actorType,
			$actorId,
			$newName
		);
	}
}

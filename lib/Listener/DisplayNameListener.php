<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\BanService;
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
		private BanService $banService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof UserChangedEvent && $event->getFeature() === 'displayName') {
			$this->updateCachedName(Attendee::ACTOR_USERS, $event->getUser()->getUID(), (string)$event->getValue());
		}
		if ($event instanceof GroupChangedEvent && $event->getFeature() === 'displayName') {
			$this->updateCachedName(Attendee::ACTOR_GROUPS, $event->getGroup()->getGID(), (string)$event->getValue());
		}
	}

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

		$this->banService->updateDisplayNameForActor(
			$actorType,
			$actorId,
			$newName
		);
	}
}

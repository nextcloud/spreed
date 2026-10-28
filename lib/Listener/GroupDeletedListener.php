<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Config;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;

/**
 * @template-implements IEventListener<Event>
 */
class GroupDeletedListener implements IEventListener {
	public function __construct(
		private readonly Manager $manager,
		private readonly ParticipantService $participantService,
		private readonly IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof GroupDeletedEvent)) {
			// Unrelated
			return;
		}

		$gid = $event->getGroup()->getGID();

		$this->removeGroupFromConfig(Config::ALLOWED_GROUPS_SIP, $gid);
		$this->removeGroupFromConfig(Config::ALLOWED_START_CALLS_GROUPS, $gid);
		$this->removeGroupFromConfig(Config::ALLOWED_GROUPS_CONVERSATIONS, $gid);
		$this->removeGroupFromConfig(Config::ALLOWED_GROUPS_TALK, $gid);

		// Remove the group itself from being a participant
		$rooms = $this->manager->getRoomsForActor(Attendee::ACTOR_GROUPS, $gid);
		foreach ($rooms as $room) {
			$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_GROUPS, $gid);
			$this->participantService->removeAttendee($room, $participant, AAttendeeRemovedEvent::REASON_REMOVED);
		}
	}

	protected function removeGroupFromConfig(string $configKey, string $removeGroupId): void {
		$gids = $this->appConfig->getAppValueArray($configKey);

		$gids = array_filter($gids, static fn ($gid) => $gid !== $removeGroupId);

		$this->appConfig->setAppValueArray($configKey, $gids);
	}
}

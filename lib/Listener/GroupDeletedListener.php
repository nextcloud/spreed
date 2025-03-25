<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ParticipantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\IConfig;

/**
 * @template-implements IEventListener<Event>
 */
class GroupDeletedListener implements IEventListener {

	public function __construct(
		private IConfig $config,
		private Manager $manager,
		private ParticipantService $participantService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof GroupDeletedEvent)) {
			// Unrelated
			return;
		}

		$gid = $event->getGroup()->getGID();

		$this->removeGroupFromConfig('sip_bridge_groups', $gid);
		$this->removeGroupFromConfig('start_conversations', $gid);
		$this->removeGroupFromConfig('allowed_groups', $gid);

		// Remove the group itself from being a participant
		$rooms = $this->manager->getRoomsForActor(Attendee::ACTOR_GROUPS, $gid);
		foreach ($rooms as $room) {
			$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_GROUPS, $gid);
			$this->participantService->removeAttendee($room, $participant, AAttendeeRemovedEvent::REASON_REMOVED);
		}
	}

	protected function removeGroupFromConfig(string $configKey, string $removeGroupId): void {
		$json = $this->config->getAppValue('spreed', $configKey, '[]');
		$array = json_decode($json, true);
		$gids = \is_array($array) ? $array : [];

		$gids = array_filter($gids, static function ($gid) use ($removeGroupId) {
			return $gid !== $removeGroupId;
		});

		$this->config->setAppValue('spreed', $configKey, json_encode($gids));
	}
}

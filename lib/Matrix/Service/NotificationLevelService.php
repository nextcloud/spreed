<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Exceptions\ParticipantNotFoundException;

/**
 * Initialises the Talk notification level of a Matrix conversation from the
 * user's Matrix push rules once; afterwards the level is Talk-local.
 */
class NotificationLevelService {
	public function __construct(
		private readonly ParticipantService $participantService,
	) {
	}

	/**
	 * @param array<string, mixed>|null $pushRules content of m.push_rules
	 */
	public function applyInitialLevel(Room $room, Account $account, MatrixRoom $matrixRoom, ?array $pushRules, bool $justCreated): void {
		if (!$justCreated) {
			return;
		}
		try {
			$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $account->getUserId());
		} catch (ParticipantNotFoundException) {
			return;
		}
		$level = $this->levelFor($matrixRoom, $pushRules);
		if ($level !== null && $participant->getAttendee()->getNotificationLevel() !== $level) {
			$this->participantService->updateNotificationLevel($participant, $level);
		}
	}

	/**
	 * @param array<string, mixed>|null $pushRules
	 */
	public function levelFor(MatrixRoom $matrixRoom, ?array $pushRules): ?int {
		$roomRules = $pushRules['global']['room'] ?? [];
		if (is_array($roomRules)) {
			foreach ($roomRules as $rule) {
				if (!is_array($rule) || ($rule['rule_id'] ?? '') !== $matrixRoom->getMatrixRoomId() || !($rule['enabled'] ?? true)) {
					continue;
				}
				$actions = is_array($rule['actions'] ?? null) ? $rule['actions'] : [];
				if ($actions === [] || in_array('dont_notify', $actions, true)) {
					return Participant::NOTIFY_NEVER;
				}
				return Participant::NOTIFY_ALWAYS;
			}
		}
		if ($matrixRoom->getIsDirect()) {
			return Participant::NOTIFY_ALWAYS;
		}
		return Participant::NOTIFY_MENTION;
	}
}

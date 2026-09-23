<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Settings\UserPreference;
use OCP\Comments\IComment;
use OCP\IConfig;

/**
 * Unarchive conversations for users who opted in via the
 * "conversations_unarchive" user setting when a new chat message is posted
 */
class ConversationUnarchiveService {
	public function __construct(
		private readonly AttendeeMapper $attendeeMapper,
		private readonly ParticipantService $participantService,
		private readonly IConfig $serverConfig,
	) {
	}

	public function unarchiveAfterMessage(Room $room, IComment $comment, ?Participant $sender, bool $silent, ?IComment $parent): void {
		$archivedAttendees = $this->attendeeMapper->getArchivedActorsByType($room->getId(), Attendee::ACTOR_USERS);
		if (empty($archivedAttendees)) {
			return;
		}

		$userIds = array_map(static fn (Attendee $attendee): string => $attendee->getActorId(), $archivedAttendees);
		$modes = $this->serverConfig->getUserValueForUsers('spreed', UserPreference::CONVERSATIONS_UNARCHIVE, $userIds);

		$senderAttendeeId = $sender?->getAttendee()->getId();
		$mentionedUserIds = $silent ? [] : $this->getMentionedUserIds($comment, $parent);
		$everyoneMentioned = !$silent && $this->isEveryoneMentioned($comment);

		$attendeeIds = [];
		foreach ($archivedAttendees as $attendee) {
			$mode = $modes[$attendee->getActorId()] ?? UserPreference::CONVERSATIONS_UNARCHIVE_NEVER;
			if ($mode === UserPreference::CONVERSATIONS_UNARCHIVE_NEVER) {
				continue;
			}

			if ($attendee->getId() === $senderAttendeeId) {
				$attendeeIds[] = $attendee->getId();
				continue;
			}

			if ($silent) {
				continue;
			}

			if ($mode === UserPreference::CONVERSATIONS_UNARCHIVE_ALWAYS
				|| $everyoneMentioned
				|| in_array($attendee->getActorId(), $mentionedUserIds, true)) {
				$attendeeIds[] = $attendee->getId();
			}
		}

		$this->participantService->unarchiveAttendeesByIds($attendeeIds);
	}

	/**
	 * Users that are directly mentioned in the message or whose message is replied to
	 *
	 * @return list<string>
	 */
	protected function getMentionedUserIds(IComment $comment, ?IComment $parent): array {
		$userIds = [];
		foreach ($comment->getMentions() as $mention) {
			if ($mention['type'] === 'user') {
				$userIds[] = $mention['id'];
			}
		}

		if ($parent instanceof IComment && $parent->getActorType() === Attendee::ACTOR_USERS) {
			$userIds[] = $parent->getActorId();
		}

		return $userIds;
	}

	protected function isEveryoneMentioned(IComment $comment): bool {
		foreach ($comment->getMentions() as $mention) {
			if ($mention['type'] === 'call') {
				return true;
			}
		}

		return false;
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Config;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\ProxyCacheMessage;
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
		private readonly Config $talkConfig,
		private readonly UserConverter $userConverter,
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
	 * Handle a message of a federated conversation for the local participant
	 * that was notified about it by the hosting server
	 */
	public function unarchiveAfterFederatedMessage(Room $room, Participant $participant, ProxyCacheMessage $message): void {
		$attendee = $participant->getAttendee();
		if (!$attendee->isArchived()
			|| $attendee->getActorType() !== Attendee::ACTOR_USERS
			|| !empty($message->getSystemMessage())) {
			return;
		}

		$mode = $this->talkConfig->getConversationsUnarchive($attendee->getActorId());
		if ($mode === UserPreference::CONVERSATIONS_UNARCHIVE_NEVER) {
			return;
		}

		if ($message->getActorType() === $attendee->getActorType()
			&& $message->getActorId() === $attendee->getActorId()) {
			$this->participantService->unarchiveConversation($participant);
			return;
		}

		$metaData = $message->getParsedMetaData();
		if (!empty($metaData[Message::METADATA_SILENT])) {
			return;
		}

		if ($mode === UserPreference::CONVERSATIONS_UNARCHIVE_ALWAYS
			|| $this->isMentionedInFederatedMessage($room, $attendee, $message, $metaData)) {
			$this->participantService->unarchiveConversation($participant);
		}
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

	/**
	 * @param array{replyToActorType?: string, replyToActorId?: string} $metaData
	 */
	protected function isMentionedInFederatedMessage(Room $room, Attendee $attendee, ProxyCacheMessage $message, array $metaData): bool {
		foreach ($message->getParsedMessageParameters() as $parameter) {
			// RichObjectDefinition types, not Attendee::ACTOR_*
			if ($parameter['type'] === 'call' && $parameter['id'] === $room->getToken()) {
				return true;
			}
			if ($parameter['type'] === 'user' && $parameter['id'] === $attendee->getActorId() && empty($parameter['server'])) {
				return true;
			}
		}

		if (!isset($metaData[ProxyCacheMessage::METADATA_REPLY_TO_ACTOR_TYPE], $metaData[ProxyCacheMessage::METADATA_REPLY_TO_ACTOR_ID])) {
			return false;
		}

		$repliedTo = $this->userConverter->convertTypeAndId(
			$room,
			$metaData[ProxyCacheMessage::METADATA_REPLY_TO_ACTOR_TYPE],
			$metaData[ProxyCacheMessage::METADATA_REPLY_TO_ACTOR_ID],
		);
		return $repliedTo['type'] === $attendee->getActorType()
			&& $repliedTo['id'] === $attendee->getActorId();
	}
}

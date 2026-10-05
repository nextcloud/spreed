<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Config;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\ProxyCacheMessage;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Settings\UserPreference;
use OCP\Comments\IComment;
use OCP\IConfig;

/**
 * Unarchive conversations for users who opted in via the
 * "conversations_unarchive" user setting when a new chat message, file or object is posted.
 *
 * A conversation is unarchived when the conversation list would show a marker
 * for the message: a mention marker with "mention" (any message in one-to-one
 * conversations), an unread marker with "always".
 * Own messages create no marker and never unarchive.
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

	public function unarchiveAfterMessage(Room $room, IComment $comment, ?IComment $parent): void {
		$archivedAttendees = $this->attendeeMapper->getArchivedActorsByType($room->getId(), Attendee::ACTOR_USERS);
		if (empty($archivedAttendees)) {
			return;
		}

		$userIds = array_map(static fn (Attendee $attendee): string => $attendee->getActorId(), $archivedAttendees);
		$modes = $this->serverConfig->getUserValueForUsers('spreed', UserPreference::CONVERSATIONS_UNARCHIVE, $userIds);

		$senderUserId = $comment->getActorType() === Attendee::ACTOR_USERS ? $comment->getActorId() : null;
		$mentions = $this->getMentions($comment);
		$mentionedUserIds = $this->getMentionedUserIds($mentions, $parent);
		// Any message in a one-to-one conversation shows the mention marker
		$everyoneMentioned = $room->getType() === Room::TYPE_ONE_TO_ONE || $this->isEveryoneMentioned($mentions);

		$attendeeIds = [];
		foreach ($archivedAttendees as $attendee) {
			if ($attendee->getActorId() === $senderUserId) {
				continue;
			}

			$mode = $modes[$attendee->getActorId()] ?? UserPreference::CONVERSATIONS_UNARCHIVE_NEVER;
			if ($mode === UserPreference::CONVERSATIONS_UNARCHIVE_ALWAYS
				|| ($mode === UserPreference::CONVERSATIONS_UNARCHIVE_MENTION
					&& ($everyoneMentioned || in_array($attendee->getActorId(), $mentionedUserIds, true)))) {
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

		if ($message->getActorType() === $attendee->getActorType()
			&& $message->getActorId() === $attendee->getActorId()) {
			return;
		}

		$mode = $this->talkConfig->getConversationsUnarchive($attendee->getActorId());
		if ($mode === UserPreference::CONVERSATIONS_UNARCHIVE_ALWAYS
			|| ($mode === UserPreference::CONVERSATIONS_UNARCHIVE_MENTION
				&& $this->isMentionedInFederatedMessage($room, $attendee, $message))) {
			$this->participantService->unarchiveConversation($participant);
		}
	}

	/**
	 * Mentions of the message, or of the caption for shared files
	 *
	 * @return list<array{type: string, id: string}>
	 */
	protected function getMentions(IComment $comment): array {
		if ($comment->getVerb() !== ChatManager::VERB_OBJECT_SHARED) {
			return $comment->getMentions();
		}

		$messageDecoded = json_decode($comment->getMessage(), true);
		$caption = $messageDecoded['parameters']['metaData']['caption'] ?? null;
		if (!is_string($caption)) {
			return [];
		}

		$captionComment = clone $comment;
		$captionComment->setMessage($caption, ChatManager::MAX_CHAT_LENGTH);
		return $captionComment->getMentions();
	}

	/**
	 * Users that are directly mentioned in the message or whose message is replied to
	 *
	 * @param list<array{type: string, id: string}> $mentions
	 * @return list<string>
	 */
	protected function getMentionedUserIds(array $mentions, ?IComment $parent): array {
		$userIds = [];
		foreach ($mentions as $mention) {
			if ($mention['type'] === 'user') {
				$userIds[] = $mention['id'];
			}
		}

		if ($parent instanceof IComment && $parent->getActorType() === Attendee::ACTOR_USERS) {
			$userIds[] = $parent->getActorId();
		}

		return $userIds;
	}

	/**
	 * @param list<array{type: string, id: string}> $mentions
	 */
	protected function isEveryoneMentioned(array $mentions): bool {
		foreach ($mentions as $mention) {
			// "@all" is a user mention with the id "all" in unparsed comments
			if ($mention['type'] === 'user' && $mention['id'] === 'all') {
				return true;
			}
		}

		return false;
	}

	protected function isMentionedInFederatedMessage(Room $room, Attendee $attendee, ProxyCacheMessage $message): bool {
		foreach ($message->getParsedMessageParameters() as $parameter) {
			// RichObjectDefinition types, not Attendee::ACTOR_*
			if ($parameter['type'] === 'call' && $parameter['id'] === $room->getToken()) {
				return true;
			}
			if ($parameter['type'] === 'user' && $parameter['id'] === $attendee->getActorId() && empty($parameter['server'])) {
				return true;
			}
		}

		/** @var array{replyToActorType?: string, replyToActorId?: string} $metaData */
		$metaData = $message->getParsedMetaData();
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

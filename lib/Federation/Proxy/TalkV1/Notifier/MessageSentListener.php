<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation\Proxy\TalkV1\Notifier;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Events\ASystemMessageSentEvent;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\ReactionAddedEvent;
use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Events\SystemMessagesMultipleSentEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\ProxyCacheMessage;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Federation\ICloudIdManager;
use OCP\L10N\IFactory;

/**
 * @template-implements IEventListener<Event>
 */
class MessageSentListener implements IEventListener {
	public function __construct(
		private readonly BackendNotifier $backendNotifier,
		private readonly ParticipantService $participantService,
		private readonly ICloudIdManager $cloudIdManager,
		private readonly MessageParser $messageParser,
		private readonly IFactory $l10nFactory,
		private readonly ChatManager $chatManager,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof ChatMessageSentEvent
			&& !$event instanceof SystemMessageSentEvent
			&& !$event instanceof SystemMessagesMultipleSentEvent
			&& !$event instanceof ReactionAddedEvent) {
			return;
		}

		if (!$event->getRoom()->hasFederatedParticipants()) {
			return;
		}

		if ($event instanceof ReactionAddedEvent) {
			$this->notifyAboutReaction($event);
			return;
		}

		// Try to have as neutral as possible messages
		$l = $this->l10nFactory->get('spreed', 'en', 'en');
		$chatMessage = $this->messageParser->createMessage($event->getRoom(), null, $event->getComment(), $l);
		$this->messageParser->parseMessage($chatMessage);

		$systemMessage = $chatMessage->getMessageType() === ChatManager::VERB_SYSTEM ? $chatMessage->getMessageRaw() : '';
		if ($systemMessage !== 'message_edited'
			&& $systemMessage !== 'message_deleted'
			&& $event instanceof ASystemMessageSentEvent
			&& $event->shouldSkipLastActivityUpdate()) {
			return;
		}

		if (!$chatMessage->getVisibility()) {
			return;
		}

		$expireDate = $event->getComment()->getExpireDate();
		$creationDate = $event->getComment()->getCreationDateTime();

		$metaData = $event->getComment()->getMetaData() ?? [];
		$parent = $event->getParent();
		if ($parent instanceof IComment) {
			$metaData[ProxyCacheMessage::METADATA_REPLY_TO_ACTOR_TYPE] = $parent->getActorType();
			$metaData[ProxyCacheMessage::METADATA_REPLY_TO_ACTOR_ID] = $parent->getActorId();
			$metaData[ProxyCacheMessage::METADATA_REPLY_TO_MESSAGE_ID] = (int)$parent->getId();
		}

		$messageData = [
			'remoteMessageId' => (int)$event->getComment()->getId(),
			'actorType' => $chatMessage->getActorType(),
			'actorId' => $chatMessage->getActorId(),
			'actorDisplayName' => $chatMessage->getActorDisplayName(),
			'messageType' => $chatMessage->getMessageType(),
			'systemMessage' => $systemMessage,
			'expirationDatetime' => $expireDate ? $expireDate->format(\DateTime::ATOM) : '',
			'message' => $chatMessage->getMessage(),
			'messageParameter' => json_encode($chatMessage->getMessageParameters(), JSON_THROW_ON_ERROR),
			'creationDatetime' => $creationDate->format(\DateTime::ATOM),
			'metaData' => json_encode($metaData, JSON_THROW_ON_ERROR),
		];

		$participants = $this->participantService->getParticipantsByActorType($event->getRoom(), Attendee::ACTOR_FEDERATED_USERS);
		$lobbyActive = $event->getRoom()->getLobbyState() !== Webinary::LOBBY_NONE;
		foreach ($participants as $participant) {
			if ($lobbyActive && !($participant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE)) {
				continue;
			}

			$attendee = $participant->getAttendee();
			$cloudId = $this->cloudIdManager->resolveCloudId($attendee->getActorId());

			$lastReadMessage = $attendee->getLastReadMessage();
			$lastMention = $attendee->getLastMentionMessage();
			$lastMentionDirect = $attendee->getLastMentionDirect();

			$unreadInfo = [
				'lastReadMessage' => $lastReadMessage,
				'unreadMessages' => $this->chatManager->getUnreadCount($event->getRoom(), $lastReadMessage),
				'unreadMention' => $lastMention !== 0 && $lastReadMessage < $lastMention,
				'unreadMentionDirect' => $lastMentionDirect !== 0 && $lastReadMessage < $lastMentionDirect,
			];

			$success = $this->backendNotifier->sendMessageUpdate(
				$cloudId->getRemote(),
				$participant->getAttendee()->getId(),
				$participant->getAttendee()->getAccessToken(),
				$event->getRoom()->getToken(),
				$messageData,
				$unreadInfo,
			);

			if ($success === null) {
				$this->participantService->removeAttendee($event->getRoom(), $participant, AAttendeeRemovedEvent::REASON_LEFT);
			}
		}
	}

	/**
	 * Reactions are no regular chat messages, so the server of the user that
	 * wrote the reacted-to message is informed with a dedicated notification,
	 * so it can notify the user when they are fully subscribed to the
	 * conversation. The notification level of federated users is only known
	 * on their home server, so it is checked there, like done for local users
	 * in {@see \OCA\Talk\Chat\Notifier::notifyReacted()}.
	 */
	protected function notifyAboutReaction(ReactionAddedEvent $event): void {
		$comment = $event->getMessage();
		if ($comment->getActorType() !== Attendee::ACTOR_FEDERATED_USERS) {
			// Local users are already handled by \OCA\Talk\Chat\Notifier::notifyReacted()
			return;
		}

		if ($comment->getActorId() === $event->getActorId()
			&& $event->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
			// User reacted to their own message
			return;
		}

		try {
			$participant = $this->participantService->getParticipantByActor(
				$event->getRoom(),
				Attendee::ACTOR_FEDERATED_USERS,
				$comment->getActorId(),
			);
		} catch (ParticipantNotFoundException) {
			return;
		}

		if ($event->getRoom()->getLobbyState() !== Webinary::LOBBY_NONE
			&& !($participant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE)) {
			// Participants in the lobby can not see the reacted-to message,
			// so no notification is sent
			return;
		}

		$reactionData = [
			'remoteMessageId' => (int)$comment->getId(),
			'actorType' => $event->getActorType(),
			'actorId' => $event->getActorId(),
			'actorDisplayName' => $event->getActorDisplayName(),
			'reaction' => $event->getReaction(),
			'creationDatetime' => ($event->getReactionMessage()?->getCreationDateTime() ?? $this->timeFactory->getDateTime())->format(\DateTime::ATOM),
		];

		$attendee = $participant->getAttendee();
		$cloudId = $this->cloudIdManager->resolveCloudId($attendee->getActorId());

		// Remote servers that do not know this notification yet answer
		// RESOURCE_NOT_FOUND. That is no reason to remove the participant,
		// so the result is not checked here.
		$this->backendNotifier->sendReactionAdded(
			$cloudId->getRemote(),
			$attendee->getId(),
			$attendee->getAccessToken(),
			$event->getRoom()->getToken(),
			$reactionData,
		);
	}
}

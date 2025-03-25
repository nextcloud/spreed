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
use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Events\SystemMessagesMultipleSentEvent;
use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\ProxyCacheMessage;
use OCA\Talk\Service\ParticipantService;
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
		protected BackendNotifier $backendNotifier,
		protected ParticipantService $participantService,
		protected ICloudIdManager $cloudIdManager,
		protected MessageParser $messageParser,
		protected IFactory $l10nFactory,
		protected ChatManager $chatManager,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof ChatMessageSentEvent
			&& !$event instanceof SystemMessageSentEvent
			&& !$event instanceof SystemMessagesMultipleSentEvent) {
			return;
		}

		// FIXME once we store/cache the info skip this if the room has no federation participant
		// if (!$event->getRoom()->hasFederatedParticipants()) {
		// return;
		// }

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
		foreach ($participants as $participant) {
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
}

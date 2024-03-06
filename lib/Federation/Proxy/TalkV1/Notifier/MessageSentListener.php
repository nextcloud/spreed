<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Federation\Proxy\TalkV1\Notifier;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
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

	public function handle(Event $event): void {
		if (!$event instanceof ChatMessageSentEvent
			&& !$event instanceof SystemMessageSentEvent
			&& !$event instanceof SystemMessagesMultipleSentEvent) {
			return;
		}

		if ($event instanceof ASystemMessageSentEvent && $event->shouldSkipLastActivityUpdate()) {
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

		if (!$chatMessage->getVisibility()) {
			return;
		}

		$expireDate = $event->getComment()->getExpireDate();
		$creationDate = $event->getComment()->getCreationDateTime();

		$metaData = $event->getComment()->getMetaData() ?? [];
		$parent = $event->getParent();
		if ($parent instanceof IComment) {
			$metaData[ProxyCacheMessage::METADATA_REPLYTO_TYPE] = $parent->getActorType();
			$metaData[ProxyCacheMessage::METADATA_REPLYTO_ID] = $parent->getActorId();
		}

		$messageData = [
			'remoteMessageId' => (int) $event->getComment()->getId(),
			'actorType' => $chatMessage->getActorType(),
			'actorId' => $chatMessage->getActorId(),
			'actorDisplayName' => $chatMessage->getActorDisplayName(),
			'messageType' => $chatMessage->getMessageType(),
			'systemMessage' => $chatMessage->getMessageType() === ChatManager::VERB_SYSTEM ? $chatMessage->getMessageRaw() : '',
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
				'unreadMessages' => $this->chatManager->getUnreadCount($event->getRoom(), $lastReadMessage),
				'unreadMention' => $lastMention !== 0 && $lastReadMessage < $lastMention,
				'unreadMentionDirect' => $lastMentionDirect !== 0 && $lastReadMessage < $lastMentionDirect
			];

			$this->backendNotifier->sendMessageUpdate(
				$cloudId->getRemote(),
				$participant->getAttendee()->getId(),
				$participant->getAttendee()->getAccessToken(),
				$event->getRoom()->getToken(),
				$messageData,
				$unreadInfo,
			);
		}
	}
}

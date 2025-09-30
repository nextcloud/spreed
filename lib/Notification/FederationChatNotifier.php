<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Notification;

use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\ProxyCacheMessage;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ThreadService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Notification\IManager;
use OCP\Notification\INotification;

class FederationChatNotifier {
	public function __construct(
		protected IAppConfig $appConfig,
		protected IManager $notificationManager,
		protected UserConverter $userConverter,
		protected ThreadService $threadService,
	) {
	}

	/**
	 * @param array{remoteServerUrl: string, sharedSecret: string, remoteToken: string, messageData: array{remoteMessageId: int, actorType: string, actorId: string, actorDisplayName: string, messageType: string, systemMessage: string, expirationDatetime: string, message: string, messageParameter: string, creationDatetime: string, metaData: string}, unreadInfo: array{unreadMessages: int, unreadMention: bool, unreadMentionDirect: bool, lastReadMessage: int}} $inboundNotification
	 */
	public function handleChatMessage(Room $room, Participant $participant, ProxyCacheMessage $message, array $inboundNotification): void {
		if (!empty($inboundNotification['messageData']['systemMessage'])) {
			return;
		}

		if ($participant->getAttendee()->getActorType() === $inboundNotification['messageData']['actorType']
			&& $participant->getAttendee()->getActorId() === $inboundNotification['messageData']['actorId']) {
			return;
		}

		/** @var array{silent?: bool, last_edited_time?: int, last_edited_by_type?: string, last_edited_by_id?: string, replyToActorType?: string, replyToActorId?: string, thread_id?: int} $metaData */
		$metaData = json_decode($inboundNotification['messageData']['metaData'] ?? '', true, flags: JSON_THROW_ON_ERROR);

		if (isset($metaData[Message::METADATA_SILENT])) {
			// Silent message, skip notification handling
			return;
		}

		$threadId = null;
		if (isset($metaData[Message::METADATA_THREAD_ID])) {
			$threadId = (int)$metaData[Message::METADATA_THREAD_ID];
		}

		if ($participant->getSession() instanceof Session && $participant->getSession()->getState() === Session::STATE_ACTIVE) {
			// User has an active session
			return;
		}


		$notificationLevel = $participant->getAttendee()->getNotificationLevel();
		if ($threadId !== null) {
			$threadAttendees = $this->threadService->findAttendeeByThreadIds($participant->getAttendee(), [$threadId]);
			$threadAttendee = $threadAttendees[$threadId] ?? null;

			if ($threadAttendee !== null
				&& $threadAttendee->getNotificationLevel() !== Participant::NOTIFY_DEFAULT) {
				$notificationLevel = $threadAttendee->getNotificationLevel();
			}
		}

		// Also notify default participants in one-to-one chats or when the admin default is "always"
		$defaultLevel = $this->appConfig->getAppValueInt('default_group_notification', Participant::NOTIFY_MENTION);
		if ($notificationLevel === Participant::NOTIFY_MENTION
			|| ($defaultLevel !== Participant::NOTIFY_NEVER && $notificationLevel === Participant::NOTIFY_DEFAULT)) {
			if ($this->isRepliedTo($room, $participant, $metaData)) {
				$notification = $this->createNotification($room, $message, 'reply', threadId: $threadId);
				$notification->setUser($participant->getAttendee()->getActorId());
				$this->notificationManager->notify($notification);
			} elseif ($this->isMentioned($participant, $message)) {
				$notification = $this->createNotification($room, $message, 'mention', threadId: $threadId);
				$notification->setUser($participant->getAttendee()->getActorId());
				$this->notificationManager->notify($notification);
			} elseif ($this->isMentionedAll($room, $message)) {
				$notification = $this->createNotification($room, $message, 'mention_all', threadId: $threadId);
				$notification->setUser($participant->getAttendee()->getActorId());
				$this->notificationManager->notify($notification);
			}
		} elseif ($participant->getAttendee()->getNotificationLevel() === Participant::NOTIFY_ALWAYS
			|| ($defaultLevel === Participant::NOTIFY_ALWAYS && $notificationLevel === Participant::NOTIFY_DEFAULT)) {
			if ($this->isUserMessageOrRelevantSystemMessage($message->getSystemMessage())) {
				$notification = $this->createNotification($room, $message, 'chat', threadId: $threadId);
				$notification->setUser($participant->getAttendee()->getActorId());
				$this->notificationManager->notify($notification);
			}
		}
	}

	/**
	 * @param array{silent?: bool, last_edited_time?: int, last_edited_by_type?: string, last_edited_by_id?: string, replyToActorType?: string, replyToActorId?: string, thread_id?: int} $metaData
	 */
	protected function isRepliedTo(Room $room, Participant $participant, array $metaData): bool {
		if (!isset($metaData[ProxyCacheMessage::METADATA_REPLY_TO_ACTOR_TYPE])
			|| !isset($metaData[ProxyCacheMessage::METADATA_REPLY_TO_ACTOR_ID])
			|| $metaData[ProxyCacheMessage::METADATA_REPLY_TO_ACTOR_TYPE] !== Attendee::ACTOR_FEDERATED_USERS) {
			return false;
		}

		$repliedTo = $this->userConverter->convertTypeAndId($room, $metaData[ProxyCacheMessage::METADATA_REPLY_TO_ACTOR_TYPE], $metaData[ProxyCacheMessage::METADATA_REPLY_TO_ACTOR_ID]);
		return $repliedTo['type'] === $participant->getAttendee()->getActorType()
			&& $repliedTo['id'] === $participant->getAttendee()->getActorId();
	}

	protected function isMentioned(Participant $participant, ProxyCacheMessage $message): bool {
		if ($participant->getAttendee()->getActorType() !== Attendee::ACTOR_USERS) {
			return false;
		}

		foreach ($message->getParsedMessageParameters() as $parameter) {
			if ($parameter['type'] === 'user' // RichObjectDefinition, not Attendee::ACTOR_USERS
				&& $parameter['id'] === $participant->getAttendee()->getActorId()
				&& empty($parameter['server'])) {
				return true;
			}
		}

		return false;
	}

	protected function isMentionedAll(Room $room, ProxyCacheMessage $message): bool {
		foreach ($message->getParsedMessageParameters() as $parameter) {
			if ($parameter['type'] === 'call' // RichObjectDefinition
				&& $parameter['id'] === $room->getToken()) {
				return true;
			}
		}

		return false;
	}

	protected function isUserMessageOrRelevantSystemMessage(?string $systemMessage): bool {
		return $systemMessage === null
			|| $systemMessage === ''
			|| $systemMessage === 'object_shared'
			|| $systemMessage === 'poll_closed'
			|| $systemMessage === 'file_shared';
	}

	/**
	 * Creates a notification for the given proxy message and mentioned users
	 */
	protected function createNotification(Room $chat, ProxyCacheMessage $message, string $subject, array $subjectData = [], ?int $threadId = null): INotification {
		$subjectData['userType'] = $message->getActorType();
		$subjectData['userId'] = $message->getActorId();

		$notificationParameters = [
			'proxyId' => $message->getId(),
		];
		if ($threadId !== null) {
			$notificationParameters['threadId'] = $threadId;
		}

		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('spreed')
			->setObject('chat', $chat->getToken())
			->setSubject($subject, $subjectData)
			->setMessage($message->getMessageType(), $notificationParameters)
			->setDateTime($message->getCreationDatetime());

		return $notification;
	}
}

<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Notification;

use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\ProxyCacheMessage;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Notification\IManager;
use OCP\Notification\INotification;

class FederationChatNotifier {
	public function __construct(
		protected IAppConfig $appConfig,
		protected IManager $notificationManager,
		protected UserConverter $userConverter,
	) {
	}

	/**
	 * @param array{remoteServerUrl: string, sharedSecret: string, remoteToken: string, messageData: array{remoteMessageId: int, actorType: string, actorId: string, actorDisplayName: string, messageType: string, systemMessage: string, expirationDatetime: string, message: string, messageParameter: string, creationDatetime: string, metaData: string}, unreadInfo: array{unreadMessages: int, unreadMention: bool, unreadMentionDirect: bool}} $inboundNotification
	 */
	public function handleChatMessage(Room $room, Participant $participant, ProxyCacheMessage $message, array $inboundNotification): void {
		$metaData = json_decode($inboundNotification['messageData']['metaData'] ?? '', true, flags: JSON_THROW_ON_ERROR);

		if (isset($metaData[Message::METADATA_SILENT])) {
			// Silent message, skip notification handling
			return;
		}

		// Also notify default participants in one-to-one chats or when the admin default is "always"
		$defaultLevel = $this->appConfig->getAppValueInt('default_group_notification', Participant::NOTIFY_MENTION);
		if ($participant->getAttendee()->getNotificationLevel() === Participant::NOTIFY_MENTION
			|| ($defaultLevel !== Participant::NOTIFY_NEVER && $participant->getAttendee()->getNotificationLevel() === Participant::NOTIFY_DEFAULT)) {
			if ($this->isRepliedTo($room, $participant, $metaData)) {
				$notification = $this->createNotification($room, $message, 'reply');
				$notification->setUser($participant->getAttendee()->getActorId());
				$this->notificationManager->notify($notification);
			} elseif ($this->isMentioned($participant, $message)) {
				$notification = $this->createNotification($room, $message, 'mention');
				$notification->setUser($participant->getAttendee()->getActorId());
				$this->notificationManager->notify($notification);
			} elseif ($this->isMentionedAll($room, $message)) {
				$notification = $this->createNotification($room, $message, 'mention_all');
				$notification->setUser($participant->getAttendee()->getActorId());
				$this->notificationManager->notify($notification);
			}
		} elseif ($participant->getAttendee()->getNotificationLevel() === Participant::NOTIFY_ALWAYS
			|| ($defaultLevel === Participant::NOTIFY_ALWAYS && $participant->getAttendee()->getNotificationLevel() === Participant::NOTIFY_DEFAULT)) {
			$notification = $this->createNotification($room, $message, 'chat');
			$notification->setUser($participant->getAttendee()->getActorId());
			$this->notificationManager->notify($notification);
		}
	}

	protected function isRepliedTo(Room $room, Participant $participant, array $metaData): bool {
		if ($metaData[ProxyCacheMessage::METADATA_REPLYTO_TYPE] !== Attendee::ACTOR_FEDERATED_USERS) {
			return false;
		}

		$repliedTo = $this->userConverter->convertTypeAndId($room, $metaData[ProxyCacheMessage::METADATA_REPLYTO_TYPE], $metaData[ProxyCacheMessage::METADATA_REPLYTO_ID]);
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

	/**
	 * Creates a notification for the given proxy message and mentioned users
	 */
	protected function createNotification(Room $chat, ProxyCacheMessage $message, string $subject, array $subjectData = []): INotification {
		$subjectData['userType'] = $message->getActorType();
		$subjectData['userId'] = $message->getActorId();

		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('spreed')
			->setObject('chat', $chat->getToken())
			->setSubject($subject, $subjectData)
			->setMessage($message->getMessageType(), [
				'proxyId' => $message->getId(),
				// FIXME Store more info to allow querying remote?
			])
			->setDateTime($message->getCreationDatetime());

		return $notification;
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat;

use OCA\Talk\Events\MessageParseEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\ProxyCacheMessage;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\BotService;
use OCA\Talk\Service\ParticipantService;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Helper class to get a rich message from a plain text message.
 */
class MessageParser {

	protected array $guestNames = [];
	protected array $federatedUsersNames = [];
	protected array $bots = [];
	protected array $botNames = [];

	public function __construct(
		protected IEventDispatcher $dispatcher,
		protected IUserManager $userManager,
		protected ParticipantService $participantService,
		protected BotService $botService,
	) {
	}

	public function createMessage(Room $room, ?Participant $participant, IComment $comment, IL10N $l): Message {
		return new Message($room, $participant, $comment, $l);
	}

	public function createMessageFromProxyCache(Room $room, ?Participant $participant, ProxyCacheMessage $proxy, IL10N $l): Message {
		$message = new Message($room, $participant, null, $l, $proxy);

		$message->setActor(
			$proxy->getActorType(),
			$proxy->getActorId(),
			$proxy->getActorDisplayName() ?? '',
		);

		$message->setMessageType($proxy->getMessageType());

		$message->setMessage(
			$proxy->getMessage(),
			$proxy->getParsedMessageParameters()
		);

		return $message;
	}

	/**
	 * @param bool $allowInaccurate File share messages will not have fully correct data for the file object
	 *                              E.g. path is only the file name and preview generation is estimated by
	 *                              mimetype only. This is done to prevent a filesystem setup.
	 */
	public function parseMessage(Message $message, bool $allowInaccurate = false): void {
		$message->setMessage($message->getComment()->getMessage(), []);

		$verb = $message->getComment()->getVerb();
		if ($verb === ChatManager::VERB_OBJECT_SHARED) {
			$verb = ChatManager::VERB_SYSTEM;
		}
		$message->setMessageType($verb);
		$this->setMessageActor($message);
		$this->setLastEditInfo($message);

		$event = new MessageParseEvent($message->getRoom(), $message, $allowInaccurate);
		$this->dispatcher->dispatchTyped($event);
	}

	protected function setMessageActor(Message $message): void {
		[$actorType, $actorId, $displayName] = $this->getActorInformation(
			$message,
			$message->getComment()->getActorType(),
			$message->getComment()->getActorId()
		);

		$message->setActor(
			$actorType,
			$actorId,
			$displayName
		);
	}

	protected function setLastEditInfo(Message $message): void {
		$metaData = $message->getComment()->getMetaData();
		if (!empty($metaData)) {
			if (isset($metaData['last_edited_by_type'], $metaData['last_edited_by_id'], $metaData['last_edited_time'])) {
				[$actorType, $actorId, $displayName] = $this->getActorInformation(
					$message,
					$metaData['last_edited_by_type'],
					$metaData['last_edited_by_id'],
					$metaData['last_edited_by_displayname'] ?? '',
				);

				$message->setLastEdit(
					$actorType,
					$actorId,
					$displayName,
					$metaData['last_edited_time']
				);
			}
		}
	}

	protected function getActorInformation(Message $message, string $actorType, string $actorId, string $displayName = ''): array {
		if ($actorType === Attendee::ACTOR_USERS) {
			$tempDisplayName = $this->userManager->getDisplayName($actorId);
			if ($tempDisplayName === null) {
				$user = $this->userManager->get($actorId);
				if (!$user instanceof IUser) {
					// Deleted user
					return [
						ICommentsManager::DELETED_USER,
						ICommentsManager::DELETED_USER,
						'',
					];
				}
				$displayName = $user->getDisplayName();
			} else {
				$displayName = $tempDisplayName;
			}
		} elseif ($actorType === Attendee::ACTOR_BRIDGED) {
			$displayName = $actorId;
			$actorId = MatterbridgeManager::BRIDGE_BOT_USERID;
		} elseif (($actorType === Attendee::ACTOR_GUESTS || $actorType === Attendee::ACTOR_EMAILS)
			&& !in_array($actorId, [Attendee::ACTOR_ID_CLI, Attendee::ACTOR_ID_SYSTEM, Attendee::ACTOR_ID_CHANGELOG, Attendee::ACTOR_ID_SAMPLE], true)) {
			$cacheKey = $actorType . '/' . $actorId;
			if (isset($this->guestNames[$cacheKey])) {
				$displayName = $this->guestNames[$cacheKey];
			} else {
				try {
					$participant = $this->participantService->getParticipantByActor($message->getRoom(), $actorType, $actorId);
					$displayName = $participant->getAttendee()->getDisplayName();
				} catch (ParticipantNotFoundException) {
				}
				$this->guestNames[$cacheKey] = $displayName;
			}
		} elseif ($actorType === Attendee::ACTOR_BOTS) {
			$displayName = $actorId . '-bot';
			$token = $message->getRoom()->getToken();
			if (str_starts_with($actorId, Attendee::ACTOR_BOT_PREFIX)) {
				$urlHash = substr($actorId, strlen(Attendee::ACTOR_BOT_PREFIX));
				$botName = $this->getBotNameByUrlHashForConversation($token, $urlHash);
				if ($botName) {
					$displayName = $botName . ' (Bot)';
				}
			}
		} elseif ($actorType === Attendee::ACTOR_FEDERATED_USERS) {
			if (isset($this->federatedUsersNames[$actorId])) {
				$displayName = $this->federatedUsersNames[$actorId];
			} else {
				$displayName = $actorId;
				try {
					$participant = $this->participantService->getParticipantByActor($message->getRoom(), Attendee::ACTOR_FEDERATED_USERS, $actorId);
					$displayName = $participant->getAttendee()->getDisplayName();
				} catch (ParticipantNotFoundException) {
					// FIXME Read from some addressbooks?
				}
				$this->federatedUsersNames[$actorId] = $displayName;
			}
		}

		return [
			$actorType,
			$actorId,
			$displayName
		];
	}

	protected function getBotNameByUrlHashForConversation(string $token, string $urlHash): ?string {
		if (!isset($this->botNames[$token])) {
			$this->botNames[$token] = [];
			$bots = $this->botService->getBotsForToken($token, null);
			foreach ($bots as $bot) {
				$botServer = $bot->getBotServer();
				$this->botNames[$token][$botServer->getUrlHash()] = $botServer->getName();
			}
		}

		return $this->botNames[$token][$urlHash] ?? null;
	}
}

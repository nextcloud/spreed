<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\ProxyCacheMessage;
use OCA\Talk\Model\Reminder;
use OCA\Talk\Model\ReminderMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Comments\IComment;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

class ReminderService {
	public function __construct(
		protected IManager $notificationManager,
		protected ReminderMapper $reminderMapper,
		protected ChatManager $chatManager,
		protected ProxyCacheMessageService $pcmService,
		protected Manager $manager,
		protected LoggerInterface $logger,
	) {
	}

	public function getUpcomingReminders(string $userId, int $limit): array {
		return $this->reminderMapper->findForUser($userId, $limit);
	}

	public function setReminder(string $userId, string $token, int $messageId, int $timestamp): Reminder {
		try {
			$reminder = $this->reminderMapper->findForUserAndMessage($userId, $token, $messageId);
			$reminder->setDateTime(new \DateTime('@' . $timestamp));
			$this->reminderMapper->update($reminder);

		} catch (DoesNotExistException) {
			$reminder = new Reminder();
			$reminder->setUserId($userId);
			$reminder->setToken($token);
			$reminder->setMessageId($messageId);
			$reminder->setDateTime(new \DateTime('@' . $timestamp));
			$this->reminderMapper->insert($reminder);
		}

		return $reminder;
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getReminder(string $userId, string $token, int $messageId): Reminder {
		return $this->reminderMapper->findForUserAndMessage($userId, $token, $messageId);
	}

	public function deleteReminder(string $userId, string $token, int $messageId): void {
		try {
			$reminder = $this->reminderMapper->findForUserAndMessage($userId, $token, $messageId);
			$this->reminderMapper->delete($reminder);
		} catch (DoesNotExistException) {
			// When the reminder does not exist anymore, the notification could be there
			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($userId)
				->setObject('reminder', $token)
				->setMessage('reminder', [
					'commentId' => $messageId,
				]);
			$this->notificationManager->markProcessed($notification);
		}
	}

	public function executeReminders(\DateTime $executeBefore): void {
		$reminders = $this->reminderMapper->findRemindersToExecute($executeBefore);

		if (empty($reminders)) {
			return;
		}

		$shouldFlush = $this->notificationManager->defer();

		$roomTokens = [];
		foreach ($reminders as $reminder) {
			$roomTokens[] = $reminder->getToken();
		}
		$roomTokens = array_unique($roomTokens);
		$rooms = $this->manager->getRoomsByToken($roomTokens);

		/** @var array<string, ProxyCacheMessage> $proxyMessages */
		$proxyMessages = [];
		$messageIds = [];
		foreach ($reminders as $reminder) {
			if (!isset($rooms[$reminder->getToken()])) {
				$this->logger->warning('Ignoring reminder for user ' . $reminder->getUserId() . ' as conversation ' . $reminder->getToken() . ' could not be found');
				continue;
			}

			$room = $rooms[$reminder->getToken()];
			if (!$room->isFederatedConversation()) {
				$messageIds[] = $reminder->getMessageId();
			} else {
				$key = json_encode([$room->getRemoteServer(), $room->getRemoteToken(), $reminder->getMessageId()]);
				if (!isset($proxyMessages[$key])) {
					try {
						$proxyMessages[$key] = $this->pcmService->findByRemote($room->getRemoteServer(), $room->getRemoteToken(), $reminder->getMessageId());
					} catch (DoesNotExistException) {
					}
				}
			}
		}

		$messageIds = array_unique($messageIds);

		$messages = $this->chatManager->getMessagesById($messageIds);

		foreach ($reminders as $reminder) {
			if (!isset($rooms[$reminder->getToken()])) {
				continue;
			}

			$room = $rooms[$reminder->getToken()];
			if (!$room->isFederatedConversation()) {
				$key = $reminder->getMessageId();
				$messageList = $messages;
				$messageParameters = [
					'commentId' => $reminder->getMessageId(),
				];
			} else {
				$key = json_encode([$room->getRemoteServer(), $room->getRemoteToken(), $reminder->getMessageId()]);
				$messageList = $proxyMessages;
				$messageParameters = [
					'proxyId' => $messageList[$key]?->getId(),
				];
			}

			if (!isset($messageList[$key])) {
				$this->logger->warning('Ignoring reminder for user ' . $reminder->getUserId() . ' as messages #' . $reminder->getMessageId() . ' could not be found for conversation ' . $reminder->getToken());
				continue;
			}
			$message = $messageList[$key];

			if ($message instanceof IComment
				&& ($message->getObjectType() !== 'chat'
					|| $room->getId() !== (int)$message->getObjectId())) {
				$this->logger->warning('Ignoring reminder for user ' . $reminder->getUserId() . ' as messages #' . $reminder->getMessageId() . ' could not be found for conversation ' . $reminder->getToken());
				continue;
			}

			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($reminder->getUserId())
				->setObject('reminder', $reminder->getToken())
				->setDateTime($reminder->getDateTime())
				->setSubject('reminder', [
					'token' => $reminder->getToken(),
					'message' => $reminder->getMessageId(),
					'userType' => $message->getActorType(),
					'userId' => $message->getActorId(),
				])
				->setMessage('reminder', $messageParameters);
			$this->notificationManager->notify($notification);
		}

		if ($shouldFlush) {
			$this->notificationManager->flush();
		}

		$this->reminderMapper->deleteExecutedReminders($executeBefore);
	}
}

<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Service;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Model\Reminder;
use OCA\Talk\Model\ReminderMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Notification\IManager;

class ReminderService {
	public function __construct(
		protected IManager $notificationManager,
		protected ReminderMapper $reminderMapper,
		protected ChatManager $chatManager,
	) {
	}

	public function setReminder(string $userId, string $token, int $messageId, int $timestamp): Reminder {
		try {
			$reminder = $this->reminderMapper->findForUserAndMessage($userId, $messageId);
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
	public function getReminder(string $userId, int $messageId): Reminder {
		return $this->reminderMapper->findForUserAndMessage($userId, $messageId);
	}

	public function deleteReminder(string $userId, string $token, int $messageId): void {
		try {
			$reminder = $this->reminderMapper->findForUserAndMessage($userId, $messageId);
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

		$messageIds = [];
		foreach ($reminders as $reminder) {
			$messageIds[] = $reminder->getMessageId();
		}
		$messageIds = array_unique($messageIds);

		$messages = $this->chatManager->getMessagesById($messageIds);

		foreach ($reminders as $reminder) {
			if (!isset($messages[$reminder->getMessageId()])) {
				continue;
			}
			$message = $messages[$reminder->getMessageId()];

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
				->setMessage('reminder', [
					'commentId' => $reminder->getMessageId(),
				]);
			$this->notificationManager->notify($notification);
		}

		if ($shouldFlush) {
			$this->notificationManager->flush();
		}

		$this->reminderMapper->deleteExecutedReminders($executeBefore);
	}
}

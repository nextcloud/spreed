<?php

declare(strict_types=1);

/*
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

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\AppInfo\Application;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\Job;
use OCP\Notification\IManager;

class ChatMessageReminder extends Job {
	public function __construct(
		ITimeFactory $time,
		protected IManager $notificationManager,
	) {
		parent::__construct($time);
	}

	public function start(IJobList $jobList): void {
		$executeAfter = $this->argument['execute-after'] ?? 0;
		if ($this->time->getTime() >= $executeAfter) {
			parent::start($jobList);
		}
	}

	/**
	 * @psalm-param array{token: string, message: string, user: string, execute-after: int} $argument
	 */
	protected function run($argument): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($argument['user'])
			->setObject('chat', $argument['message'])
			->setDateTime($this->time->getDateTime('@' . $this->argument['execute-after']))
			->setSubject('reminder', [
				'token' => $argument['token'],
			]);
		$this->notificationManager->notify($notification);
	}
}

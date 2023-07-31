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
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

class ChatMessageReminder extends Job {
	public function __construct(
		ITimeFactory $time,
		protected IDBConnection $connection,
		protected IManager $notificationManager,
		protected LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	public function start(IJobList $jobList): void {
		$timeUntilExecution = $this->argument['execute-after'] - $this->time->getTime();

		if ($timeUntilExecution <= 0) {
			parent::start($jobList);
			$jobList->remove($this, $this->argument);
		} elseif ($timeUntilExecution > 900) {
			// Execution is quite far in the future. In order to not check the
			// job too often, we update it's test time to be closer to the execution
			$this->setLastRunCloseToExecutionTime(
				$this->argument['execute-after'],
				$this->argument['token'],
				$this->argument['user'],
				$this->argument['message'],
			);
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

	protected function setLastRunCloseToExecutionTime(int $timestamp, string $token, string $userId, string $message): void {
		$query = $this->connection->getQueryBuilder();

		$query->update('jobs')
			->set('last_run', $query->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->executeStatement();

		$this->logger->debug('Updated chat message reminder last_run to ' . $timestamp . ' for token "' . $token . '" user "' . $userId . '" message ' . $message);
	}
}

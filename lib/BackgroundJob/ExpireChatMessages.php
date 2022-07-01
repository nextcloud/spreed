<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;

class ExpireChatMessages extends TimedJob {
	private IJobList $jobList;
	private Manager $roomManager;
	private ChatManager $chatManager;

	public function __construct(ITimeFactory $timeFactory,
								IJobList $jobList,
								Manager $roomManager,
								ChatManager $chatManager) {
		parent::__construct($timeFactory);
		$this->jobList = $jobList;
		$this->roomManager = $roomManager;
		$this->chatManager = $chatManager;

		// Every 5 minutes
		$this->setInterval(5 * 60);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	/**
	 * @param array $argument
	 */
	protected function run($argument): void {
		$this->chatManager->deleteExpiredMessages($argument['room_id']);

		try {
			$room = $this->roomManager->getRoomById($argument['room_id']);
			if ($room->getMessageExpiration() === 0) {
				// FIXME check if there are still messages to expire in the database
				$this->jobList->remove(ExpireChatMessages::class, $argument);
			}
		} catch (RoomNotFoundException $e) {
			$this->jobList->remove(ExpireChatMessages::class, $argument);
		}
	}
}

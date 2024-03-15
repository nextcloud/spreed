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
use OCA\Talk\Service\ProxyCacheMessageService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

class ExpireChatMessages extends TimedJob {

	public function __construct(
		ITimeFactory $timeFactory,
		private ChatManager $chatManager,
		private ProxyCacheMessageService $pcmService,
	) {
		parent::__construct($timeFactory);

		// Every 5 minutes
		$this->setInterval(5 * 60);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument): void {
		$this->chatManager->deleteExpiredMessages();
		$this->pcmService->deleteExpiredMessages();
	}
}

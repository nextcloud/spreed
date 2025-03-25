<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	#[\Override]
	protected function run($argument): void {
		$this->chatManager->deleteExpiredMessages();
		$this->pcmService->deleteExpiredMessages();
	}
}

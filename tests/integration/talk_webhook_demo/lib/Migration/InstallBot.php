<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TalkWebhookDemo\Migration;

use OCA\Talk\Events\BotInstallEvent;
use OCA\TalkWebhookDemo\Service\BotService;
use OCP\IURLGenerator;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class InstallBot implements IRepairStep {
	public function __construct(
		protected IURLGenerator $url,
		protected BotService $service,
	) {
	}

	public function getName(): string {
		return 'Install as Talk bot';
	}

	public function run(IOutput $output): void {
		if (!class_exists(BotInstallEvent::class)) {
			$output->warning('Talk not found, not installing bots');
			return;
		}

		$backend = $this->url->getAbsoluteURL('');
		$this->service->installBot($backend);
	}
}

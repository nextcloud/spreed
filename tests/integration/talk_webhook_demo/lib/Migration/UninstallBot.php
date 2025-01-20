<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TalkWebhookDemo\Migration;

use OCA\Talk\Events\BotUninstallEvent;
use OCA\TalkWebhookDemo\Service\BotService;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class UninstallBot implements IRepairStep {
	public function __construct(
		protected IConfig $config,
		protected IURLGenerator $url,
		protected BotService $service,
	) {
	}

	public function getName(): string {
		return 'Uninstall Talk bots';
	}

	public function run(IOutput $output): void {
		if (!class_exists(BotUninstallEvent::class)) {
			$output->warning('Talk not found, not removing the bots');
			return;
		}

		$backend = $this->url->getAbsoluteURL('');
		$id = sha1($backend);

		$secretData = $this->config->getAppValue('talk_webhook_demo', 'secret_' . $id);
		if ($secretData) {
			$secretArray = json_decode($secretData, true, 512, JSON_THROW_ON_ERROR);
			if ($secretArray['secret']) {
				$this->service->uninstallBot($secretArray['secret'], $backend);
			}
		}
	}
}

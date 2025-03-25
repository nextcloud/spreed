<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\SetupCheck;

use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class NotifyPush implements ISetupCheck {

	public function __construct(
		protected IL10N $l10n,
		protected IAppManager $appManager,
	) {
	}

	#[\Override]
	public function getName(): string {
		return $this->l10n->t('Client Push'); // TRANSLATORS: this is the app name of the notify_push app.
	}

	#[\Override]
	public function getCategory(): string {
		return 'talk';
	}

	#[\Override]
	public function run(): SetupResult {
		if ($this->appManager->isEnabledForAnyone('notify_push')) {
			return SetupResult::success(
				$this->l10n->t('Client Push is installed, this improves the performance of desktop clients.')
			);
		}

		return SetupResult::warning(
			$this->l10n->t('{notify_push} is not installed, this might lead to performance issues when using desktop clients.'),
			'https://github.com/nextcloud/notify_push/blob/main/README.md',
			[
				'notify_push' => [
					'id' => 'notify_push',
					'name' => $this->getName(),
					'type' => 'app',
				],
			],
		);
	}
}

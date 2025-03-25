<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\SetupCheck;

use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

/**
 * Check app configs and their dependencies
 */
class Configuration implements ISetupCheck {
	public function __construct(
		protected IL10N $l10n,
		protected IConfig $config,
		protected IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function getCategory(): string {
		return 'talk';
	}

	#[\Override]
	public function getName(): string {
		return $this->l10n->t('Talk configuration values');
	}

	#[\Override]
	public function run(): SetupResult {
		$errors = $warnings = [];
		$maxCallDuration = $this->appConfig->getAppValueInt('max_call_duration');
		if ($maxCallDuration > 0) {
			if ($this->config->getAppValue('core', 'backgroundjobs_mode', 'ajax') !== 'cron') {
				$errors[] = $this->l10n->t('Forcing a call duration is only supported with system cron. Please enable system cron or remove the `max_call_duration` configuration.');
			} elseif ($maxCallDuration < 3600) {
				$warnings[] = $this->l10n->t('Small `max_call_duration` values (currently set to %d) are not enforceable due to technical limitations. The background job is only executed every 5 minutes, so use at own risk.', [$maxCallDuration]);
			}
		}

		if (!empty($errors)) {
			return SetupResult::error(implode("\n", $errors));
		}
		if (!empty($warnings)) {
			return SetupResult::warning(implode("\n", $warnings));
		}
		return SetupResult::success();
	}
}

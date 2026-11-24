<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\SetupCheck;

use OCA\Talk\Config;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IAppConfig as IServerAppConfig;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

/**
 * Check app configs and their dependencies
 */
class Configuration implements ISetupCheck {
	public function __construct(
		private readonly IL10N $l10n,
		private readonly IAppConfig $appConfig,
		private readonly IServerAppConfig $serverAppConfig,
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
		$maxCallDuration = $this->appConfig->getAppValueInt(Config::MAX_CALL_DURATION);
		if ($maxCallDuration > 0) {
			if ($this->serverAppConfig->getValueString('core', 'backgroundjobs_mode', 'ajax') !== 'cron') {
				$errors[] = $this->l10n->t('Forcing a call duration is only supported with system cron. Please enable system cron or remove the `max_call_duration` configuration.');
			} elseif ($maxCallDuration < 3600) {
				$warnings[] = $this->l10n->t('Small `max_call_duration` values (currently set to %d) are not enforceable due to technical limitations. The background job is only executed every 5 minutes, so use at own risk.', [$maxCallDuration]);
			}
		}

		$publicKey = $this->appConfig->getAppValueString(Config::EXTERNAL_CALL_SERVICE_PUBLIC_KEY);
		if ($publicKey !== '' && !$this->isEs256PublicKey($publicKey)) {
			$errors[] = $this->l10n->t('The `external_call_service_public_key` configuration is not a valid PEM encoded ECDSA P-256 public key. Requests from the external call service can not be verified.');
		}

		if (!empty($errors)) {
			return SetupResult::error(implode("\n", $errors));
		}
		if (!empty($warnings)) {
			return SetupResult::warning(implode("\n", $warnings));
		}
		return SetupResult::success();
	}

	protected function isEs256PublicKey(string $publicKey): bool {
		$key = openssl_pkey_get_public($publicKey);
		if ($key === false) {
			return false;
		}

		$details = openssl_pkey_get_details($key);
		return $details !== false
			&& $details['type'] === OPENSSL_KEYTYPE_EC
			&& ($details['ec']['curve_name'] ?? '') === 'prime256v1';
	}
}

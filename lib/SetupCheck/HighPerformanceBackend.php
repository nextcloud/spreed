<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\SetupCheck;

use OCA\Talk\Config;
use OCA\Talk\Signaling\Manager;
use OCP\AppFramework\Http;
use OCP\ICacheFactory;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;
use OCP\Support\Subscription\IRegistry;

class HighPerformanceBackend implements ISetupCheck {
	public function __construct(
		readonly protected Config $talkConfig,
		readonly protected ICacheFactory $cacheFactory,
		readonly protected IURLGenerator $urlGenerator,
		readonly protected IL10N $l,
		readonly protected Manager $signalManager,
		readonly protected IRegistry $subscription,
	) {
	}

	#[\Override]
	public function getCategory(): string {
		return 'talk';
	}

	#[\Override]
	public function getName(): string {
		return $this->l->t('High-performance backend');
	}

	#[\Override]
	public function run(): SetupResult {
		if ($this->talkConfig->getSignalingMode() === Config::SIGNALING_INTERNAL) {
			$setupResult = SetupResult::error(...);
			if ($this->talkConfig->getHideSignalingWarning()) {
				$setupResult = SetupResult::info(...);
			}
			$documentation = 'https://nextcloud-talk.readthedocs.io/en/latest/quick-install/';
			if ($this->subscription->delegateHasValidSubscription()) {
				$documentation = 'https://portal.nextcloud.com/article/Nextcloud-Talk/High-Performance-Backend/Installation-of-Nextcloud-Talk-High-Performance-Backend';
			}

			return $setupResult(
				$this->l->t('No High-performance backend configured - Running Nextcloud Talk without the High-performance backend only scales for very small calls (max. 2-3 participants). Please set up the High-performance backend to ensure calls with multiple participants work seamlessly.'),
				$documentation,
			);
		}

		if ($this->talkConfig->getSignalingMode() === Config::SIGNALING_CLUSTER_CONVERSATION) {
			return SetupResult::warning(
				$this->l->t('Running the High-performance backend "conversation_cluster" mode is deprecated and will no longer be supported in the upcoming version. The High-performance backend supports real clustering nowadays which should be used instead.'),
				'https://portal.nextcloud.com/article/Partner-Products/Talk-High-Performance-Backend/Nextcloud-Talk-High-Performance-Back-End-Requirements#content-clustering-and-use-of-multiple-hpbs',
			);
		}

		if (count($this->talkConfig->getSignalingServers()) > 1) {
			return SetupResult::warning(
				$this->l->t('Defining multiple High-performance backends is deprecated and will no longer be supported in the upcoming version. Instead a load-balancer should be set up together with clustered signaling servers and configured in the Talk settings.'),
				'https://portal.nextcloud.com/article/Partner-Products/Talk-High-Performance-Backend/Nextcloud-Talk-High-Performance-Back-End-Requirements#content-clustering-and-use-of-multiple-hpbs',
			);
		}

		try {
			$testResult = $this->signalManager->checkServerCompatibility(0);
		} catch (\OutOfBoundsException) {
			return SetupResult::error($this->l->t('High-performance backend not configured correctly'));
		}
		if ($testResult['status'] === Http::STATUS_INTERNAL_SERVER_ERROR) {
			$error = $testResult['data']['error'];
			if ($error === 'CAN_NOT_CONNECT') {
				return SetupResult::error($this->l->t('Error: Cannot connect to server'));
			}
			if ($error === 'JSON_INVALID') {
				return SetupResult::error($this->l->t('Error: Server did not respond with proper JSON'));
			}
			if ($error === 'CERTIFICATE_EXPIRED') {
				return SetupResult::error($this->l->t('Error: Certificate expired'));
			}
			if ($error === 'TIME_OUT_OF_SYNC') {
				return SetupResult::error($this->l->t('Error: System times of Nextcloud server and High-performance backend server are out of sync. Please make sure that both servers are connected to a time-server or manually synchronize their time.'));
			}
			if ($error === 'UPDATE_REQUIRED') {
				$version = $testResult['data']['version'] ?? $this->l->t('Could not get version');
				return SetupResult::error(str_replace(
					'{version}',
					$version,
					$this->l->t('Error: Running version: {version}; Server needs to be updated to be compatible with this version of Talk'),
				));
			}
			if ($error) {
				return SetupResult::error(str_replace('{error}', $error, $this->l->t('Error: Server responded with: {error}')));
			}
			return SetupResult::error($this->l->t('Error: Unknown error occurred'));
		}
		if ($testResult['status'] === Http::STATUS_OK
			&& isset($testResult['data']['warning'])
			&& $testResult['data']['warning'] === 'UPDATE_OPTIONAL'
		) {
			$version = $testResult['data']['version'] ?? $this->l->t('Could not get version');
			$features = implode(', ', $testResult['data']['features'] ?? []);
			return SetupResult::warning(str_replace(
				['{version}', '{features}'],
				[$version, $features],
				$this->l->t('Warning: Running version: {version}; Server does not support all features of this Talk version, missing features: {features}')
			));
		}

		if (!$this->cacheFactory->isAvailable()) {
			return SetupResult::warning(
				$this->l->t('It is highly recommended to configure a memory cache when running Nextcloud Talk with a High-performance backend.'),
				$this->urlGenerator->linkToDocs('admin-cache'),
			);
		}

		return SetupResult::success();
	}
}

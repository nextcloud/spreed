<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\SetupCheck;

use OCA\Talk\Config;
use OCP\ICacheFactory;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class HighPerformanceBackend implements ISetupCheck {
	public function __construct(
		readonly protected Config $talkConfig,
		readonly protected ICacheFactory $cacheFactory,
		readonly protected IURLGenerator $urlGenerator,
		readonly protected IL10N $l,
	) {
	}

	public function getCategory(): string {
		return 'talk';
	}

	public function getName(): string {
		return $this->l->t('High-performance backend');
	}

	public function run(): SetupResult {
		if ($this->talkConfig->getSignalingMode() === Config::SIGNALING_INTERNAL) {
			return SetupResult::success();
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

		if ($this->cacheFactory->isAvailable()) {
			return SetupResult::success();
		}
		return SetupResult::warning(
			$this->l->t('It is highly recommended to configure a memory cache when running Nextcloud Talk with a High-performance backend.'),
			$this->urlGenerator->linkToDocs('admin-cache'),
		);
	}
}

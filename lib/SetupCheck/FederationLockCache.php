<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\SetupCheck;

use OC\Memcache\NullCache;
use OCA\Talk\Config;
use OCP\ICacheFactory;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class FederationLockCache implements ISetupCheck {
	public function __construct(
		protected readonly Config $talkConfig,
		protected readonly ICacheFactory $cacheFactory,
		protected readonly IURLGenerator $urlGenerator,
		protected readonly IL10N $l,
	) {
	}

	#[\Override]
	public function getCategory(): string {
		return 'talk';
	}

	#[\Override]
	public function getName(): string {
		return $this->l->t('Federation');
	}

	#[\Override]
	public function run(): SetupResult {
		if (!$this->talkConfig->isFederationEnabled()) {
			return SetupResult::success();
		}
		if (!$this->cacheFactory->createLocking('talkroom_') instanceof NullCache) {
			return SetupResult::success();
		}
		return SetupResult::warning(
			$this->l->t('It is highly recommended to configure "memcache.locking" when Talk Federation is enabled.'),
			$this->urlGenerator->linkToDocs('admin-cache'),
		);
	}
}

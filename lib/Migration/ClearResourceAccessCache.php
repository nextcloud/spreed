<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use OCA\Talk\Collaboration\Resources\ConversationProvider;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Collaboration\Resources\IManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class ClearResourceAccessCache implements IRepairStep {
	protected const INVALIDATIONS = 1;

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IManager $manager,
		private readonly ConversationProvider $provider,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Invalidate access cache for projects conversation provider';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$invalidatedCache = $this->appConfig->getAppValueInt('project_access_invalidated', 0);

		if ($invalidatedCache === self::INVALIDATIONS) {
			$output->info('Invalidation not required');
			return;
		}

		$this->manager->invalidateAccessCacheForProvider($this->provider);
		$this->appConfig->setAppValueInt('project_access_invalidated', self::INVALIDATIONS);
	}
}

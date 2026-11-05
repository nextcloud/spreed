<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\SetupCheck;

use OCA\Talk\Config;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class ExpensiveRepairSteps implements ISetupCheck {
	public const int VERSION = 1;

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IDBConnection $connection,
		private readonly IL10N $l,
	) {
	}

	#[\Override]
	public function getCategory(): string {
		return 'talk';
	}

	#[\Override]
	public function getName(): string {
		return $this->l->t('Repair steps');
	}

	#[\Override]
	public function run(): SetupResult {
		if ($this->appConfig->getAppValueInt(Config::EXPENSIVE_REPAIR_PERFORMED) >= self::VERSION) {
			return SetupResult::success();
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('id')
			->from('talk_attachments')
			->setMaxResults(1);
		$result = $query->executeQuery();
		$hasAttachments = $result->fetchOne() !== false;
		$result->closeCursor();

		if (!$hasAttachments) {
			return SetupResult::success();
		}

		return SetupResult::warning(
			$this->l->t('Talk has repair steps that take a long time on larger instances, so they are not performed automatically during upgrades. Use the command `occ maintenance:repair --include-expensive` to perform them.'),
		);
	}
}

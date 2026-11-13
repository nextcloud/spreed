<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCA\Talk\BackgroundJob\IndexLinkAttachments;
use OCP\BackgroundJob\IJobList;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Schedule indexing links of chat messages sent before the upgrade
 */
class Version26000Date20261113120000 extends SimpleMigrationStep {
	public function __construct(
		protected IJobList $jobList,
		protected IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->select($query->func()->max('id'))
			->from('comments');
		$maxId = $query->executeQuery()->fetchOne();

		if ($maxId !== null && $maxId !== false) {
			// Newer messages are indexed when they are sent
			$this->jobList->add(IndexLinkAttachments::class, ['beforeId' => (int)$maxId + 1]);
		}
	}
}

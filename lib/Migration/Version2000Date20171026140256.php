<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Migration;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2000Date20171026140256 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $connection,
		protected IConfig $config,
		protected IGroupManager $groupManager,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @since 13.0.0
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		if (version_compare($this->config->getAppValue('spreed', 'installed_version', '0.0.0'), '2.0.0', '<')) {
			// Migrations only work after 2.0.0
			return;
		}

		$update = $this->connection->getQueryBuilder();
		$update->update('spreedme_rooms')
			->set('name', $update->createNamedParameter(''))
			->where($update->expr()->eq('id', $update->createParameter('room_id')));

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('spreedme_rooms');
		$result = $query->executeQuery();

		$output->startProgress();
		while ($row = $result->fetch()) {
			$output->advance();

			if (strlen($row['name']) !== 12 || $this->groupManager->groupExists($row['name'])) {
				continue;
			}

			$update->setParameter('room_id', (int)$row['id'], IQueryBuilder::PARAM_INT)
				->executeStatement();
		}
		$output->finishProgress();
	}
}

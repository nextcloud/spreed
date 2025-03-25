<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class FixNamespaceInDatabaseTables implements IRepairStep {

	public function __construct(
		protected IDBConnection $connection,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Fix the namespace in database tables';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('jobs')
			->set('class', $update->createParameter('newClass'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		$query = $this->connection->getQueryBuilder();
		$query->select('id', 'class')
			->from('jobs')
			->where($query->expr()->like('class', $query->createNamedParameter(
				'%' . $this->connection->escapeLikeParameter('Spreed') . '%'
			)));

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$oldClass = $row['class'];
			if (!str_starts_with($oldClass, 'OCA\\Spreed\\')) {
				continue;
			}

			$newClass = 'OCA\\Talk\\' . substr($oldClass, strlen('OCA\\Spreed\\'));

			$update->setParameter('newClass', $newClass)
				->setParameter('id', $row['id']);
			$update->executeStatement();
		}
		$result->closeCursor();
	}
}

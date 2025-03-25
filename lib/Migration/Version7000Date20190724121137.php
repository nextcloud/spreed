<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version7000Date20190724121137 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @since 13.0.0
	 */
	#[\Override]
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->select('p.user_id', 'p.room_id')
			->selectAlias($query->createFunction('MAX(' . $query->getColumnName('c.id') . ')'), 'last_mention_message')
			->from('talk_participants', 'p')
			->leftJoin('p', 'comments', 'c', $query->expr()->andX(
				$query->expr()->eq('c.object_id', $query->expr()->castColumn('p.room_id', IQueryBuilder::PARAM_STR)),
				$query->expr()->eq('c.object_type', $query->createNamedParameter('chat')),
				$query->expr()->eq('c.creation_timestamp', 'p.last_mention')
			))
			->where($query->expr()->isNotNull('p.user_id'))
			->groupBy('p.user_id', 'p.room_id');

		$update = $this->connection->getQueryBuilder();
		$update->update('talk_participants')
			->set('last_mention_message', $update->createParameter('message_id'))
			->where($update->expr()->eq('user_id', $update->createParameter('user_id')))
			->andWhere($update->expr()->eq('room_id', $update->createParameter('room_id')));

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$update->setParameter('message_id', (int)$row['last_mention_message'], IQueryBuilder::PARAM_INT)
				->setParameter('user_id', $row['user_id'])
				->setParameter('room_id', (int)$row['room_id'], IQueryBuilder::PARAM_INT);
			$update->executeStatement();
		}
		$result->closeCursor();
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws SchemaException
	 * @since 13.0.0
	 */
	#[\Override]
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_participants');
		if ($table->hasColumn('last_mention')) {
			$table->dropColumn('last_mention');
		}

		return $schema;
	}
}

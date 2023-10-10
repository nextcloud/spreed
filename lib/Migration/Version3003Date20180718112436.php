<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Talk\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version3003Date20180718112436 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 13.0.0
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_rooms');
		if (!$table->hasColumn('last_activity')) {
			$table->addColumn('last_activity', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->addIndex(['last_activity'], 'last_active');
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @since 13.0.0
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_rooms')
			->set('last_activity', $update->createParameter('activity'))
			->where($update->expr()->eq('id', $update->createParameter('room')));

		$query = $this->connection->getQueryBuilder();
		$query->select('object_id')
			->selectAlias($query->createFunction('MAX(' . $query->getColumnName('creation_timestamp') . ')'), 'last_activity')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter('chat')))
			->groupBy('object_id');

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$lastActivity = new \DateTime($row['last_activity']);
			$update->setParameter('activity', $lastActivity, IQueryBuilder::PARAM_DATE)
				->setParameter('room', $row['object_id']);
			$update->executeStatement();
		}
		$result->closeCursor();
	}
}

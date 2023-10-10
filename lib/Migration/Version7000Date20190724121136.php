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

use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version7000Date20190724121136 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws SchemaException
	 * @since 13.0.0
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_participants');
		if (!$table->hasColumn('last_read_message')) {
			$table->addColumn('last_read_message', Types::BIGINT, [
				'default' => 0,
				'notnull' => false,
			]);
			$table->addColumn('last_mention_message', Types::BIGINT, [
				'default' => 0,
				'notnull' => false,
			]);
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @since 13.0.0
	 *
	 * @return void
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		$query = $this->connection->getQueryBuilder();
		$query->select('m.user_id', 'm.object_id')
			->selectAlias($query->createFunction('MAX(' . $query->getColumnName('c.id') . ')'), 'last_comment')
			->from('comments_read_markers', 'm')
			->leftJoin('m', 'comments', 'c', $query->expr()->andX(
				$query->expr()->eq('c.object_id', 'm.object_id'),
				$query->expr()->eq('c.object_type', 'm.object_type'),
				$query->expr()->eq('c.creation_timestamp', 'm.marker_datetime')
			))
			->where($query->expr()->eq('m.object_type', $query->createNamedParameter('chat')))
			->groupBy('m.user_id', 'm.object_id');

		$update = $this->connection->getQueryBuilder();
		$update->update('talk_participants')
			->set('last_read_message', $update->createParameter('message_id'))
			->where($update->expr()->eq('user_id', $update->createParameter('user_id')))
			->andWhere($update->expr()->eq('room_id', $update->createParameter('room_id')));

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$update->setParameter('message_id', (int) $row['last_comment'], IQueryBuilder::PARAM_INT)
				->setParameter('user_id', $row['user_id'])
				->setParameter('room_id', (int) $row['object_id'], IQueryBuilder::PARAM_INT);
			$update->executeStatement();
		}
		$result->closeCursor();

		/**
		 * The above query only works if the user read in the same exact second
		 * as the comment was posted (author only), we set the read marker to -1
		 * for all users and in case of -1 we calculate the marker on the next request.
		 */
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_participants')
			->set('last_read_message', $update->createNamedParameter(-1))
			->where($update->expr()->isNotNull('user_id'))
			->andWhere($update->expr()->eq('last_read_message', $update->createNamedParameter(0)));
		$update->executeStatement();
	}
}

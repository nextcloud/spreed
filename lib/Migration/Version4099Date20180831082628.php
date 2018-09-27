<?php
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
namespace OCA\Spreed\Migration;

use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version4099Date20180831082628 extends SimpleMigrationStep {

	/** @var IDBConnection */
	protected $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @since 13.0.0
	 */
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		$query = $this->connection->getQueryBuilder();
		$query->select('p.user_id', 'p.room_id')
			->selectAlias($query->createFunction('MAX(' . $query->getColumnName('c.id') . ')'), 'last_mention_message')
			->from('talk_participants', 'p')
			->leftJoin('p', 'comments', 'c', $query->expr()->andX(
				$query->expr()->eq('c.object_id', 'p.room_id'),
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

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$update->setParameter('message_id', (int) $row['last_mention_message'], IQueryBuilder::PARAM_INT)
				->setParameter('user_id', $row['user_id'])
				->setParameter('room_id', (int) $row['room_id'], IQueryBuilder::PARAM_INT);
			$update->execute();
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
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_participants');
		if ($table->hasColumn('last_mention')) {
			$table->dropColumn('last_mention');
		}

		return $schema;
	}
}

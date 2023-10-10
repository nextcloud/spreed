<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version11000Date20200922161218 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('talk_bridges')) {
			$table = $schema->getTable('talk_bridges');
			if (!$table->hasColumn('enabled')) {
				$table->addColumn('enabled', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				]);
			}
			if (!$table->hasColumn('pid')) {
				$table->addColumn('pid', Types::INTEGER, [
					'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				]);
			}
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();

		$bridges = [];
		$query->select('id', 'json_values')
			->from('talk_bridges');
		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$bridges[] = [
				'id' => $row['id'],
				'json_values' => $row['json_values'],
			];
		}
		$result->closeCursor();

		if (empty($bridges)) {
			return;
		}

		$update = $this->connection->getQueryBuilder();
		$update->update('talk_bridges')
			->set('enabled', $update->createParameter('enabled'))
			->set('pid', $update->createParameter('pid'))
			->set('json_values', $update->createParameter('json_values'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		foreach ($bridges as $bridge) {
			$values = json_decode($bridge['json_values'], true);
			if (isset($values['pid'], $values['enabled'])) {
				$intEnabled = $values['enabled'] ? 1 : 0;
				$newValues = $values['parts'] ?: [];
				$encodedNewValues = json_encode($newValues);

				$update->setParameter('enabled', $intEnabled, IQueryBuilder::PARAM_INT)
					->setParameter('pid', $values['pid'], IQueryBuilder::PARAM_INT)
					->setParameter('json_values', $encodedNewValues, IQueryBuilder::PARAM_STR)
					->setParameter('id', $bridge['id'], IQueryBuilder::PARAM_INT);
				$update->executeStatement();
			}
		}
	}
}

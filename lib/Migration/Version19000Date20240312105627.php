<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add table to queue federation notifications to retry
 */
class Version19000Date20240312105627 extends SimpleMigrationStep {
	public function __construct(
		protected IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->createTable('talk_retry_ocm');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('remote_server', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('num_attempts', Types::INTEGER, [
			'default' => 0,
			'unsigned' => true,
		]);
		$table->addColumn('next_retry', Types::DATETIME, [
			'notnull' => false,
		]);
		$table->addColumn('notification_type', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('resource_type', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('provider_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('notification', Types::TEXT, [
			'notnull' => true,
		]);


		$table->setPrimaryKey(['id']);
		$table->addIndex(['next_retry'], 'talk_retry_ocm_next');

		return $schema;
	}

	/**
	 * Remove legacy RetryJobs
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @psalm-suppress UndefinedClass */
		$formerClassName = \OCA\Talk\BackgroundJob\RetryJob::class;

		$query = $this->connection->getQueryBuilder();
		$query->delete('jobs')
			->where($query->expr()->eq('class', $query->createNamedParameter($formerClassName)));
		$query->executeStatement();
	}
}

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	#[\Override]
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
	#[\Override]
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @psalm-suppress UndefinedClass */
		$formerClassName = \OCA\Talk\BackgroundJob\RetryJob::class;

		$query = $this->connection->getQueryBuilder();
		$query->delete('jobs')
			->where($query->expr()->eq('class', $query->createNamedParameter($formerClassName)));
		$query->executeStatement();
	}
}

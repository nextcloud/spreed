<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Override;

class Version24000Date20260510193300 extends SimpleMigrationStep {

	public function __construct(
		private readonly IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_rooms');

		if (!$table->hasColumn('last_metadata_activity')) {
			$table->addColumn('last_metadata_activity', Types::DATETIME, [
				'notnull' => false,
			]);
			$table->addIndex(['last_metadata_activity'], 'talkthread_lastmetadataactive');

		}

		return $schema;
	}
	
	/**
 	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) : void {
	   $update = $this->connection->getQueryBuilder();
	   $update->update('talk_rooms')
               ->set('last_metadata_activity', 'last_activity');
       $update->executeStatement();
	}	

}

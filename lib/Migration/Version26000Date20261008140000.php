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
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Matrix accounts linked by users
 */
#[CreateTable(table: 'talk_matrix_accounts', columns: ['id', 'user_id', 'homeserver_id', 'mxid', 'access_token', 'device_id'], description: 'Matrix accounts linked by users')]
class Version26000Date20261008140000 extends SimpleMigrationStep {
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

		if ($schema->hasTable('talk_matrix_accounts')) {
			return null;
		}

		$table = $schema->createTable('talk_matrix_accounts');
		$table->addColumn('id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
		$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('homeserver_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
		$table->addColumn('mxid', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('access_token', Types::TEXT, ['notnull' => false]);
		$table->addColumn('device_id', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['user_id'], 'tma_user_id');
		$table->addIndex(['homeserver_id', 'user_id'], 'tma_homeserver');

		return $schema;
	}
}

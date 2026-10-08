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

#[CreateTable(table: 'talk_matrix_homeservers', columns: ['id', 'name', 'server_name', 'base_url', 'enabled', 'versions_json', 'versions_fetched'], description: 'Matrix homeservers users may link accounts on')]
class Version26000Date20261008120000 extends SimpleMigrationStep {
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

		if ($schema->hasTable('talk_matrix_homeservers')) {
			return null;
		}

		$table = $schema->createTable('talk_matrix_homeservers');
		$table->addColumn('id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('server_name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('base_url', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('enabled', Types::BOOLEAN, ['notnull' => false, 'default' => 1]);
		$table->addColumn('versions_json', Types::TEXT, ['notnull' => false, 'default' => null]);
		$table->addColumn('versions_fetched', Types::DATETIME, ['notnull' => false, 'default' => null]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['server_name'], 'tmh_server_name');

		return $schema;
	}
}

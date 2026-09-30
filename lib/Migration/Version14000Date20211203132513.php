<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\Attributes\AddIndex;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\Attributes\DropColumn;
use OCP\Migration\Attributes\IndexType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[AddColumn(table: 'talk_rooms', name: 'remote_server', type: ColumnType::STRING)]
#[AddColumn(table: 'talk_rooms', name: 'remote_token', type: ColumnType::STRING)]
#[AddIndex(table: 'talk_rooms', type: IndexType::INDEX)]
#[DropColumn(table: 'talk_rooms', name: 'server_url')]
class Version14000Date20211203132513 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_rooms');
		if (!$table->hasColumn('remote_server')) {
			$table->addColumn('remote_server', Types::STRING, [
				'notnull' => false,
				'length' => 512,
				'default' => null,
			]);
			$table->addColumn('remote_token', Types::STRING, [
				'notnull' => false,
				'length' => 32,
				'default' => null,
			]);

			// Can not be unique as we have null, null for all local rooms.
			$table->addIndex(['remote_server', 'remote_token'], 'remote_id');
		}

		if ($table->hasColumn('server_url')) {
			$table->dropColumn('server_url');
		}

		return $schema;
	}
}

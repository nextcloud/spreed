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
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Track whether the homeserver still accepts the access token of a linked Matrix account
 */
#[AddColumn(table: 'talk_matrix_accounts', name: 'status', type: ColumnType::SMALLINT)]
#[AddColumn(table: 'talk_matrix_accounts', name: 'last_error', type: ColumnType::TEXT)]
class Version26000Date20261008150000 extends SimpleMigrationStep {
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

		$table = $schema->getTable('talk_matrix_accounts');
		if ($table->hasColumn('status')) {
			return null;
		}

		$table->addColumn('status', Types::SMALLINT, ['notnull' => true, 'default' => 0, 'unsigned' => true]);
		$table->addColumn('last_error', Types::TEXT, ['notnull' => false, 'default' => null]);
		return $schema;
	}
}

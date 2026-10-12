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
 * Restoring room keys from the key backup of a Matrix account
 */
#[AddColumn(table: 'talk_matrix_accounts', name: 'backup_key', type: ColumnType::TEXT, description: 'Encrypted key and version of the key backup')]
class Version26000Date20261014140000 extends SimpleMigrationStep {
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
		if ($table->hasColumn('backup_key')) {
			return null;
		}
		$table->addColumn('backup_key', Types::TEXT, ['notnull' => false]);
		return $schema;
	}
}

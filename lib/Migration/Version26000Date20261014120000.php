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
 * Devices of the user that were verified with emoji
 */
#[AddColumn(table: 'talk_matrix_devices', name: 'verified', type: ColumnType::BOOLEAN, description: 'Verified with emoji, keys forwarded by the device are accepted')]
class Version26000Date20261014120000 extends SimpleMigrationStep {
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

		$table = $schema->getTable('talk_matrix_devices');
		if ($table->hasColumn('verified')) {
			return null;
		}
		$table->addColumn('verified', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
		return $schema;
	}
}

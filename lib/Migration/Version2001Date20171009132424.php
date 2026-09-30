<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[AddColumn(table: 'spreedme_rooms', name: 'activeSince', type: ColumnType::DATETIME)]
#[AddColumn(table: 'spreedme_rooms', name: 'activeGuests', type: ColumnType::INTEGER)]
class Version2001Date20171009132424 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 13.0.0
	 */
	#[\Override]
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('spreedme_rooms');
		$table->addColumn('activeSince', Types::DATETIME, [
			'notnull' => false,
		]);
		$table->addColumn('activeGuests', Types::INTEGER, [
			'notnull' => true,
			'length' => 4,
			'default' => 0,
			'unsigned' => true,
		]);

		return $schema;
	}
}

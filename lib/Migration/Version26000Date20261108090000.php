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

#[AddColumn(table: 'talk_polls', name: 'show_voters_before_closing', type: ColumnType::BOOLEAN)]
class Version26000Date20261108090000 extends SimpleMigrationStep {

	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->getTable('talk_polls');
		if ($table->hasColumn('show_voters_before_closing')) {
			return null;
		}

		$table->addColumn('show_voters_before_closing', Types::BOOLEAN, [
			'default' => false,
			'notnull' => true,
		]);

		return $schema;
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add listable column to the rooms table.
 */
class Version11000Date20201201102528 extends SimpleMigrationStep {
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

		if ($schema->hasTable('talk_rooms')) {
			$table = $schema->getTable('talk_rooms');

			if (!$table->hasColumn('listable')) {
				$table->addColumn('listable', Types::SMALLINT, [
					'notnull' => false,
					'default' => 0,
					'unsigned' => true,
				]);
			}

			if (!$table->hasIndex('tr_listable')) {
				$table->addIndex(['listable'], 'tr_listable');
			}

			return $schema;
		}

		return null;
	}
}

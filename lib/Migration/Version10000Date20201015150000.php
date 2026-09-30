<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\Attributes\ModifyColumn;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * The HPB is generating sessions longer than 255 chars. So we update the length
 * But the install migration was fixed, so this only does something on update.
 */
#[ModifyColumn(table: 'talk_sessions', name: 'session_id', type: ColumnType::STRING, description: 'increase length to 512')]
class Version10000Date20201015150000 extends SimpleMigrationStep {
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

		if ($schema->hasTable('talk_sessions')) {
			$table = $schema->getTable('talk_sessions');

			$column = $table->getColumn('session_id');

			if ($column->getLength() !== 512) {
				$column->setLength(512);
				return $schema;
			}
		}

		return null;
	}
}

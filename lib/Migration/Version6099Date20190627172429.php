<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version6099Date20190627172429 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('talk_rooms')) {
			$table = $schema->getTable('talk_rooms');

			if (!$table->hasColumn('lobby_state')) {
				$table->addColumn('lobby_state', Types::INTEGER, [
					'notnull' => true,
					'length' => 6,
					'default' => 0,
				]);
				$table->addColumn('lobby_timer', Types::DATETIME, [
					'notnull' => false,
				]);
			}
		}

		return $schema;
	}
}

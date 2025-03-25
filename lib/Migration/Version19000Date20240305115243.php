<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add creation datetime and meta-data columns to the proxy cache
 */
class Version19000Date20240305115243 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_proxy_messages');
		if (!$table->hasColumn('creation_datetime')) {
			$table->addColumn('creation_datetime', Types::DATETIME, [
				'notnull' => false,
			]);
			$table->addColumn('meta_data', Types::TEXT, [
				'notnull' => false,
			]);

			return $schema;
		}

		return null;
	}
}

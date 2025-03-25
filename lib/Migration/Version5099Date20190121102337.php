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

class Version5099Date20190121102337 extends SimpleMigrationStep {
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

		if (!$schema->hasTable('talk_commands')) {
			$table = $schema->createTable('talk_commands');

			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('app', Types::STRING, [
				'notnull' => false,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('command', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('script', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('response', Types::INTEGER, [
				'notnull' => true,
				'length' => 6,
				'default' => 1,
			]);
			$table->addColumn('enabled', Types::INTEGER, [
				'notnull' => true,
				'length' => 6,
				'default' => 1,
			]);

			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}
}

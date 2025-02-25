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

class Version22000Date20250224113228 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();


		if (!$schema->hasTable('talk_room_events')) {
			$table = $schema->createTable('talk_room_events');

			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('room_id', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('start', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('end', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('description', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);

			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}
}

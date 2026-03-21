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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version24000Date20260313120000 extends SimpleMigrationStep {
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

		if (!$schema->hasTable('talk_conversation_categories')) {
			$table = $schema->createTable('talk_conversation_categories');
			$table->addColumn('id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
				'length' => 20,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('sort_order', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('collapsed', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'tcs_user_id');
		}

		$attendeesTable = $schema->getTable('talk_attendees');
		if (!$attendeesTable->hasColumn('category_ids')) {
			$attendeesTable->addColumn('category_ids', Types::TEXT, [
				'notnull' => false,
				'default' => null,
			]);
		}

		return $schema;
	}
}

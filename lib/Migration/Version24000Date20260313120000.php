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

		if (!$schema->hasTable('talk_conversation_tags')) {
			$table = $schema->createTable('talk_conversation_tags');
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
			$table->addColumn('type', Types::STRING, [
				'notnull' => true,
				'length' => 16,
				'default' => 'custom',
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'tct_user_id');
			// Uniqueness guards:
			//  - For built-ins (type in favorites/other), name equals the type → a second
			//    insert of the same built-in for the same user collides here.
			//  - For custom tags, prevents a user from creating two tags with the same name.
			$table->addUniqueIndex(['user_id', 'type', 'name'], 'tct_user_type_name');
		}

		$attendeesTable = $schema->getTable('talk_attendees');
		if (!$attendeesTable->hasColumn('tag_ids')) {
			$attendeesTable->addColumn('tag_ids', Types::TEXT, [
				'notnull' => false,
				'default' => null,
			]);
		}

		return $schema;
	}
}

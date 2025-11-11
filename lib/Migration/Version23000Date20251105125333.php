<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

#[CreateTable(table: 'talk_scheduled_messages', columns: ['id', 'room_token', 'actor_id', 'actor_type', 'message', 'send_at', 'meta_data', 'thread_id', 'parent_id'], description: 'Supporting scheduled message sending')]
class Version23000Date20251105125333 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('talk_scheduled_messages')) {
			$table = $schema->createTable('talk_scheduled_messages');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('room_token', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('actor_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('actor_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('message', Types::TEXT, [
				'notnull' => true,
				'default' => json_encode([]),
			]);
			$table->addColumn('message_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('meta_data', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('thread_id', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('parent_id', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('send_at', Types::DATETIME, [
				'notnull' => false,
			]);
		}
		return $schema;
	}
}

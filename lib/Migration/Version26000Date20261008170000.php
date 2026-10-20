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
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Matrix events that were mirrored as chat messages
 */
#[CreateTable(table: 'talk_matrix_events', columns: ['id', 'matrix_room_id', 'event_id', 'event_type', 'sender', 'comment_id'], description: 'Matrix events that were mirrored into conversations')]
class Version26000Date20261008170000 extends SimpleMigrationStep {
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

		if ($schema->hasTable('talk_matrix_events')) {
			return null;
		}

		$table = $schema->createTable('talk_matrix_events');
		$table->addColumn('id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
		$table->addColumn('matrix_room_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'length' => 20]);
		$table->addColumn('event_id', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('event_type', Types::STRING, ['notnull' => true, 'length' => 128]);
		$table->addColumn('sender', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('comment_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'length' => 20]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['event_id'], 'tme_event_id');
		$table->addIndex(['matrix_room_id'], 'tme_room');

		return $schema;
	}
}

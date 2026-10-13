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
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Room key rotation settings of encrypted Matrix rooms
 */
#[AddColumn(table: 'talk_matrix_rooms', name: 'rotation_period_ms', type: ColumnType::BIGINT, description: 'Milliseconds after which a new room key is used, from m.room.encryption')]
#[AddColumn(table: 'talk_matrix_rooms', name: 'rotation_period_msgs', type: ColumnType::BIGINT, description: 'Messages after which a new room key is used, from m.room.encryption')]
class Version26000Date20261015120000 extends SimpleMigrationStep {
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

		$table = $schema->getTable('talk_matrix_rooms');
		if ($table->hasColumn('rotation_period_ms')) {
			return null;
		}
		$table->addColumn('rotation_period_ms', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'length' => 20]);
		$table->addColumn('rotation_period_msgs', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'length' => 20]);
		return $schema;
	}
}

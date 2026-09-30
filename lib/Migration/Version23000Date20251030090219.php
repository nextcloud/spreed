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
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

#[AddColumn(table: 'talk_rooms', name: 'last_pinned_id', type: ColumnType::BIGINT)]
#[AddColumn(table: 'talk_attendees', name: 'hidden_pinned_id', type: ColumnType::BIGINT)]
class Version23000Date20251030090219 extends SimpleMigrationStep {
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

		$table = $schema->getTable('talk_rooms');
		if (!$table->hasColumn('last_pinned_id')) {
			$table->addColumn('last_pinned_id', Types::BIGINT, [
				'notnull' => false,
				'default' => 0,
			]);
		}

		$table = $schema->getTable('talk_attendees');
		if (!$table->hasColumn('hidden_pinned_id')) {
			$table->addColumn('hidden_pinned_id', Types::BIGINT, [
				'notnull' => false,
				'default' => 0,
			]);
		}

		return $schema;
	}
}

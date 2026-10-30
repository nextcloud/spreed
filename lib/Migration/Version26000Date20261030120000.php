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
 * The time of the first message an attendee may read
 */
#[AddColumn(table: 'talk_attendees', name: 'history_since', type: ColumnType::BIGINT, description: 'Unix time of the first message the attendee may read, 0 for the whole history')]
class Version26000Date20261030120000 extends SimpleMigrationStep {
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

		$attendees = $schema->getTable('talk_attendees');
		if ($attendees->hasColumn('history_since')) {
			return null;
		}
		$attendees->addColumn('history_since', Types::BIGINT, [
			'notnull' => true,
			'default' => 0,
			'unsigned' => true,
			'length' => 20,
		]);

		return $schema;
	}
}

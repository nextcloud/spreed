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
use OCP\Migration\Attributes\AddIndex;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\Attributes\IndexType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Store the expiration of the chat message on attachments and polls,
 * so they can be cleaned up together with the message.
 * Existing entries are handled by the {@see CleanupExpiredMessageLeftovers} repair step.
 */
#[AddColumn(table: 'talk_attachments', name: 'expire_date', type: ColumnType::DATETIME)]
#[AddIndex(table: 'talk_attachments', type: IndexType::INDEX, description: 'index on expire date')]
#[AddColumn(table: 'talk_polls', name: 'expire_date', type: ColumnType::DATETIME)]
#[AddIndex(table: 'talk_polls', type: IndexType::INDEX, description: 'index on expire date')]
class Version26000Date20261031120000 extends SimpleMigrationStep {
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

		$changed = false;
		foreach (['talk_attachments' => 'talk_attach_expire', 'talk_polls' => 'talk_poll_expire'] as $tableName => $indexName) {
			$table = $schema->getTable($tableName);
			if ($table->hasColumn('expire_date')) {
				continue;
			}

			$table->addColumn('expire_date', Types::DATETIME, [
				'notnull' => false,
			]);
			$table->addIndex(['expire_date'], $indexName);
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}

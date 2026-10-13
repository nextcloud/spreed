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
 * Keys of encrypted attachments of end-to-end encrypted Matrix rooms
 */
#[AddColumn(table: 'talk_matrix_events', name: 'media_keys', type: ColumnType::TEXT, description: 'Encrypted key, IV and hash of an encrypted attachment')]
class Version26000Date20261013140000 extends SimpleMigrationStep {
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

		$table = $schema->getTable('talk_matrix_events');
		if ($table->hasColumn('media_keys')) {
			return null;
		}
		$table->addColumn('media_keys', Types::TEXT, ['notnull' => false]);
		return $schema;
	}
}

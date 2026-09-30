<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\Attributes\ModifyColumn;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adjust session id length in internal signaling messages to maximum Nextcloud
 * session id length.
 */
#[ModifyColumn(table: 'talk_internalsignaling', name: 'sender', type: ColumnType::STRING, description: 'increase length to 512')]
#[ModifyColumn(table: 'talk_internalsignaling', name: 'recipient', type: ColumnType::STRING, description: 'increase length to 512')]
class Version20000Date20240718031959 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('talk_internalsignaling')) {
			$table = $schema->getTable('talk_internalsignaling');

			$modified = false;

			$sender = $table->getColumn('sender');
			if ($sender->getLength() !== 512) {
				$sender->setLength(512);
				$modified = true;
			}

			$recipient = $table->getColumn('recipient');
			if ($recipient->getLength() !== 512) {
				$recipient->setLength(512);
				$modified = true;
			}

			if ($modified) {
				return $schema;
			}
		}

		return null;
	}
}

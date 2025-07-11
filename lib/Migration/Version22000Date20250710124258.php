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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Add the last activity (for sorting) and thread name for future feature to name a thread
 */
class Version22000Date20250710124258 extends SimpleMigrationStep {
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

		$table = $schema->getTable('talk_threads');
		if (!$table->hasColumn('last_activity')) {
			$table->addColumn('last_activity', Types::DATETIME, [
				'notnull' => false,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
				'default' => '',
			]);

			$table->addIndex(['last_activity'], 'talkthread_lastactive');
		}

		$table = $schema->getTable('talk_thread_attendees');
		if ($table->hasColumn('last_read_message')) {
			$table->dropColumn('last_read_message');
		}
		if ($table->hasColumn('last_mention_message')) {
			$table->dropColumn('last_mention_message');
		}
		if ($table->hasColumn('last_mention_direct')) {
			$table->dropColumn('last_mention_direct');
		}
		if ($table->hasColumn('read_privacy')) {
			$table->dropColumn('read_privacy');
		}

		return $schema;
	}
}

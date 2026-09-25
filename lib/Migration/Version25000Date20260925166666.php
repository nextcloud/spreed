<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Replacing @see Version25000Date20260923155555
 * and @see Version22001Date20250927174738
 */
class Version25000Date20260925166666 extends SimpleMigrationStep {
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
		$table = $schema->getTable('talk_thread_attendees');
		if ($table->hasIndex('tta_thread_attendee')) {
			$table->dropIndex('tta_thread_attendee');
			$changed = true;
		}
		if (!$table->hasIndex('tta_throom_attendee')) {
			$table->addUniqueIndex(['thread_id', 'room_id', 'actor_type', 'actor_id'], 'tta_throom_attendee');
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}

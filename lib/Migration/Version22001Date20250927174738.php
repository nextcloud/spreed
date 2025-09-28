<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version22001Date20250927174738 extends SimpleMigrationStep {
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

		$table = $schema->getTable('talk_thread_attendees');
		if ($table->hasUniqueConstraint('tta_thread_attendee')) {
			$table->removeUniqueConstraint('tta_thread_attendee');
		}
		if (!$table->hasUniqueConstraint('tta_throom_attendee')) {
			$table->addUniqueIndex(['thread_id', 'room_id', 'actor_type', 'actor_id'], 'tta_throom_attendee');
		}

		return null;
	}
}

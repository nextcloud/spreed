<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Replace the former unique attendee key with a normal index
 * allowing an attendee to have multiple sessions in the same conversation.
 */
class Version12000Date20210217134030 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_sessions');
		if ($table->hasIndex('ts_attendee')) {
			$table->dropIndex('ts_attendee');
		}
		if (!$table->hasIndex('ts_attendee_id')) {
			$table->addIndex(['attendee_id'], 'ts_attendee_id');
		}

		return $schema;
	}
}

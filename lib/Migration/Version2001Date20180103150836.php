<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2001Date20180103150836 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 13.0.0
	 */
	#[\Override]
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_rooms');
		if ($table->hasColumn('activeSince')) {
			$table->dropColumn('activeSince');
			$table->dropColumn('activeGuests');
		}

		$table = $schema->getTable('talk_participants');
		if ($table->hasColumn('userId')) {
			$table->dropColumn('userId');
			$table->dropColumn('roomId');
			$table->dropColumn('lastPing');
			$table->dropColumn('sessionId');
			$table->dropColumn('participantType');
			$table->dropColumn('inCall');
		}

		return $schema;
	}
}

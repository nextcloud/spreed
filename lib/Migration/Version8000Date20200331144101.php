<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version8000Date20200331144101 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_participants');
		if (!$table->hasIndex('tp_ident')) {
			$table->addUniqueIndex(['room_id', 'user_id', 'session_id'], 'tp_ident');
		}
		if (!$table->hasIndex('tp_room')) {
			$table->addIndex(['room_id'], 'tp_room');
		}
		if (!$table->hasIndex('tp_last_ping')) {
			$table->addIndex(['last_ping'], 'tp_last_ping');
		}
		if (!$table->hasIndex('tp_in_call')) {
			$table->addIndex(['in_call'], 'tp_in_call');
		}

		return $schema;
	}
}

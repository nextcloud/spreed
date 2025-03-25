<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version13000Date20210921142733 extends SimpleMigrationStep {
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

		$table = $schema->getTable('talk_attendees');
		if ($table->hasColumn('publishing_permissions')) {
			$table->dropColumn('publishing_permissions');
		}

		if (!$table->hasColumn('permissions')) {
			$table->addColumn('permissions', Types::INTEGER, [
				'default' => 0,
			]);
		}

		$table = $schema->getTable('talk_rooms');
		if (!$table->hasColumn('default_permissions')) {
			$table->addColumn('default_permissions', Types::INTEGER, [
				'default' => 0,
			]);
		}
		if (!$table->hasColumn('call_permissions')) {
			$table->addColumn('call_permissions', Types::INTEGER, [
				'default' => 0,
			]);
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// FIXME set default_permissions based on appconfig "start_calls"
	}
}

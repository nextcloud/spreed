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

class Version12000Date20210528100404 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		// Obsoleted by Version13000Date20210921142733
		//		/** @var ISchemaWrapper $schema */
		//		$schema = $schemaClosure();
		//
		//		$table = $schema->getTable('talk_attendees');
		//		if (!$table->hasColumn('publishing_permissions')) {
		//			$table->addColumn('publishing_permissions', Types::INTEGER, [
		//				'default' => 7,
		//			]);
		//
		//			return $schema;
		//		}

		return null;
	}
}

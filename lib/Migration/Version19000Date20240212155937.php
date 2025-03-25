<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add inviter information to the invites for rendering them outside of notifications later
 */
class Version19000Date20240212155937 extends SimpleMigrationStep {
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

		$table = $schema->getTable('talk_invitations');
		if (!$table->hasColumn('inviter_cloud_id')) {
			$table->addColumn('inviter_cloud_id', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('inviter_display_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			return $schema;
		}

		return null;
	}
}

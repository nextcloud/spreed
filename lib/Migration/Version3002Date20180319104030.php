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

class Version3002Date20180319104030 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 13.0.0
	 */
	#[\Override]
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/**
		 * The table had to be redone so it contains a primary key
		 * @see Version11000Date20201209142525
		 *
		 * $schema = $schemaClosure();
		 *
		 * if (!$schema->hasTable('talk_guests')) {
		 * $table = $schema->createTable('talk_guests');
		 *
		 * $table->addColumn('session_hash', Type::STRING, [
		 * 'notnull' => false,
		 * 'length' => 64,
		 * ]);
		 * $table->addColumn('display_name', Type::STRING, [
		 * 'notnull' => false,
		 * 'length' => 64,
		 * 'default' => '',
		 * ]);
		 *
		 * $table->addUniqueIndex(['session_hash'], 'tg_session_hash');
		 * }
		 *
		 * return $schema;
		 */
		return null;
	}
}

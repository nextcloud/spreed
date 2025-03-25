<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version11000Date20201209142525 extends SimpleMigrationStep {
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

		$changedSchema = false;
		if (!$schema->hasTable('talk_internalsignaling')) {
			$table = $schema->createTable('talk_internalsignaling');

			// Auto increment id
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);

			$table->addColumn('sender', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('recipient', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('message', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('timestamp', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['recipient', 'timestamp'], 'tis_recipient_time');

			$changedSchema = true;
		}

		if (!$schema->hasTable('talk_guestnames')) {
			$table = $schema->createTable('talk_guestnames');

			// Auto increment id
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);

			$table->addColumn('session_hash', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('display_name', Types::STRING, [
				'notnull' => false,
				'length' => 64,
				'default' => '',
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['session_hash'], 'tgn_session_hash');
			$changedSchema = true;
		}

		return $changedSchema ? $schema : null;
	}
}

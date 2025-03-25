<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2001Date20170913104501 extends SimpleMigrationStep {
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

		if (!$schema->hasTable('videocalls_signaling')) {
			$table = $schema->createTable('videocalls_signaling');

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

			$table->addIndex(['recipient', 'timestamp'], 'vcsig_recipient');
		}

		if ($schema->hasTable('spreedme_messages')) {
			$schema->dropTable('spreedme_messages');
		}

		return $schema;
	}
}

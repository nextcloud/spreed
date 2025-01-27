<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TalkWebhookDemo\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20230719061613 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('twd_log_entries')) {
			$table = $schema->createTable('twd_log_entries');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
			]);

			$table->addColumn('server', Types::STRING, [
				'notnull' => true,
				'length' => 512,
			]);
			$table->addColumn('token', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);

			$table->addColumn('type', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('details', Types::TEXT, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['server', 'token'], 'twd_log_entry_origin');
			return $schema;
		}
		return null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}

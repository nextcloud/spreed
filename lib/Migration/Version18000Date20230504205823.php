<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version18000Date20230504205823 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return ?ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('talk_bots_server')) {
			$table = $schema->createTable('talk_bots_server');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('name', Types::STRING, [
				'length' => 64,
			]);
			$table->addColumn('url', Types::STRING, [
				'length' => 4000,
			]);
			$table->addColumn('url_hash', Types::STRING, [
				'length' => 64,
			]);
			$table->addColumn('description', Types::STRING, [
				'length' => 4000,
				'notnull' => false,
			]);
			$table->addColumn('secret', Types::STRING, [
				'length' => 128,
			]);
			$table->addColumn('error_count', Types::BIGINT, [
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('last_error_date', Types::DATETIME, [
				'notnull' => false,
			]);
			$table->addColumn('last_error_message', Types::STRING, [
				'length' => 4000,
				'notnull' => false,
			]);
			$table->addColumn('state', Types::SMALLINT, [
				'default' => 0,
				'notnull' => false,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['state'], 'talk_bots_server_state');
			$table->addUniqueIndex(['url_hash'], 'talk_bots_server_urlhash');
			$table->addUniqueIndex(['secret'], 'talk_bots_server_secret');

			$table = $schema->createTable('talk_bots_conversation');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('bot_id', Types::BIGINT, [
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('token', Types::STRING, [
				'length' => 64,
				'notnull' => false,
			]);
			$table->addColumn('state', Types::SMALLINT, [
				'default' => 0,
				'notnull' => false,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['token', 'state'], 'talk_bots_convo_token');
			//$table->addIndex(['bot_id'], 'talk_bots_convo_id'); Removed in Version20000Date20240717180417
			$table->addUniqueIndex(['bot_id', 'token'], 'talk_bots_convo_uniq');
			return $schema;
		}

		return null;
	}
}

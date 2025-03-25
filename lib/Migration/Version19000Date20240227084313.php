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
 * A temporary message cache for TalkV1 proxying to serve "last message" and help with notifications
 */
class Version19000Date20240227084313 extends SimpleMigrationStep {
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

		$table = $schema->createTable('talk_proxy_messages');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('local_token', Types::STRING, [
			'notnull' => true,
			'length' => 32,
		]);
		$table->addColumn('remote_server_url', Types::STRING, [
			'notnull' => true,
			'length' => 512,
		]);
		$table->addColumn('remote_token', Types::STRING, [
			'notnull' => true,
			'length' => 32,
		]);
		$table->addColumn('remote_message_id', Types::BIGINT, [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('actor_type', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('actor_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('actor_display_name', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);
		$table->addColumn('message_type', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('system_message', Types::STRING, [
			'notnull' => false,
			'length' => 64,
		]);
		$table->addColumn('expiration_datetime', Types::DATETIME, [
			'notnull' => false,
		]);
		$table->addColumn('message', Types::TEXT, [
			'notnull' => false,
		]);
		$table->addColumn('message_parameters', Types::TEXT, [
			'notnull' => false,
		]);

		$table->setPrimaryKey(['id']);

		$table->addUniqueIndex(['remote_server_url', 'remote_token', 'remote_message_id'], 'talk_pcm_remote');
		$table->addIndex(['local_token'], 'talk_pmc_local');

		return $schema;
	}
}

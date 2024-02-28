<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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

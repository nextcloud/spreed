<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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

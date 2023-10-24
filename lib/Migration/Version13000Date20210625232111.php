<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Gary Kim <gary@garykim.dev>
 *
 * @author Gary Kim <gary@garykim.dev>
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
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version13000Date20210625232111 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('talk_attendees');
		if (!$table->hasColumn('access_token')) {
			$table->addColumn('access_token', Types::STRING, [
				'notnull' => false,
				'default' => null,
				'length' => 64
			]);
		}
		if (!$table->hasColumn('remote_id')) {
			$table->addColumn('remote_id', Types::STRING, [
				'notnull' => false,
				'default' => null,
				'length' => 255,
			]);
		}

		$table = $schema->getTable('talk_rooms');
		if (!$table->hasColumn('server_url')) {
			$table->addColumn('server_url', Types::STRING, [
				'notnull' => false,
				'default' => null,
			]);
		}

		/** Removed in {@see \OCA\Talk\Migration\Version18000Date20231024141626} */
		/** Recreated in {@see \OCA\Talk\Migration\Version18000Date20231024141627} */
		// if (!$schema->hasTable('talk_invitations')) {
		// $table = $schema->createTable('talk_invitations');
		// $table->addColumn('id', Types::BIGINT, [
		// 'autoincrement' => true,
		// 'notnull' => true,
		// ]);
		// $table->addColumn('room_id', Types::BIGINT, [
		// 'notnull' => true,
		// 'unsigned' => true,
		// ]);
		// $table->addColumn('user_id', Types::STRING, [
		// 'notnull' => true,
		// 'length' => 255,
		// ]);
		// $table->addColumn('access_token', Types::STRING, [
		// 'notnull' => true,
		// 'length' => 64,
		// ]);
		// $table->addColumn('remote_id', Types::STRING, [
		// 'notnull' => true,
		// 'length' => 255,
		// ]);
		//
		// $table->setPrimaryKey(['id']);
		//
		// $table->addIndex(['room_id']);
		// }

		return $schema;
	}
}

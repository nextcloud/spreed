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
 * Add inviter information to the invites for rendering them outside of notifications later
 */
class Version19000Date20240214095011 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$changed = false;

		$table = $schema->getTable('talk_attendees');
		if (!$table->hasColumn('invited_cloud_id')) {
			$table->addColumn('invited_cloud_id', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$changed = true;
		}

		$table = $schema->getTable('talk_invitations');
		if (!$table->hasColumn('local_cloud_id')) {
			$table->addColumn('local_cloud_id', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}

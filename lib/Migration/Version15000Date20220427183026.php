<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
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
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version15000Date20220427183026 extends SimpleMigrationStep {
	protected IDBConnection $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * Update existing permissions by adding the chat permissions when set to none default
	 *
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `\OCP\DB\ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_rooms')
			->set('default_permissions', $update->func()->add(
				'default_permissions',
				$update->createNamedParameter(128, IQueryBuilder::PARAM_INT) // Attendee::PERMISSION_CHAT
			))
			->where($update->expr()->neq('default_permissions', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))) // Attendee::PERMISSIONS_DEFAULT
			->andWhere(
				$update->expr()->neq(
					$update->expr()->castColumn(
						$update->expr()->bitwiseAnd(
							'default_permissions',
							128 // Attendee::PERMISSION_CHAT
						),
						IQueryBuilder::PARAM_INT
					),
					$update->createNamedParameter(128, IQueryBuilder::PARAM_INT) // Attendee::PERMISSION_CHAT
				)
			);
		$update->executeStatement();

		$update = $this->connection->getQueryBuilder();
		$update->update('talk_rooms')
			->set('call_permissions', $update->func()->add(
				'call_permissions',
				$update->createNamedParameter(128, IQueryBuilder::PARAM_INT) // Attendee::PERMISSION_CHAT
			))
			->where($update->expr()->neq('call_permissions', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))) // Attendee::PERMISSIONS_DEFAULT
			->andWhere(
				$update->expr()->neq(
					$update->expr()->castColumn(
						$update->expr()->bitwiseAnd(
							'call_permissions',
							128 // Attendee::PERMISSION_CHAT
						),
						IQueryBuilder::PARAM_INT
					),
					$update->createNamedParameter(128, IQueryBuilder::PARAM_INT) // Attendee::PERMISSION_CHAT
				)
			);
		$update->executeStatement();

		$update = $this->connection->getQueryBuilder();
		$update->update('talk_attendees')
			->set('permissions', $update->func()->add(
				'permissions',
				$update->createNamedParameter(128, IQueryBuilder::PARAM_INT) // Attendee::PERMISSION_CHAT
			))
			->where($update->expr()->neq('permissions', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))) // Attendee::PERMISSIONS_DEFAULT
			->andWhere(
				$update->expr()->neq(
					$update->expr()->castColumn(
						$update->expr()->bitwiseAnd(
							'permissions',
							128 // Attendee::PERMISSION_CHAT
						),
						IQueryBuilder::PARAM_INT
					),
					$update->createNamedParameter(128, IQueryBuilder::PARAM_INT) // Attendee::PERMISSION_CHAT
				)
			);
		$update->executeStatement();
	}
}

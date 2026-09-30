<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\Attributes\DataCleansing;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[DataCleansing(table: 'talk_rooms', description: 'add chat permission to custom default and call permissions')]
#[DataCleansing(table: 'talk_attendees', description: 'add chat permission to custom attendee permissions')]
class Version15000Date20220427183026 extends SimpleMigrationStep {

	public function __construct(
		private readonly IDBConnection $connection,
	) {
	}

	/**
	 * Update existing permissions by adding the chat permissions when set to none default
	 *
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `\OCP\DB\ISchemaWrapper`
	 * @param array $options
	 */
	#[\Override]
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

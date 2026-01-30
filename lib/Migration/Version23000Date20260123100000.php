<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add PERMISSIONS_REACT (256) to all records that have PERMISSIONS_CHAT (128) set.
 * This migration splits the combined "chat" permission into separate "chat" (post messages)
 * and "react" (add reactions) permissions for backward compatibility.
 */
class Version23000Date20260123100000 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Add REACT permission (256) to all room default_permissions that have CHAT (128) set
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_rooms')
			->set('default_permissions', $update->func()->add(
				'default_permissions',
				$update->createNamedParameter(256, IQueryBuilder::PARAM_INT) // Attendee::PERMISSIONS_REACT
			))
			->where($update->expr()->neq('default_permissions', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))) // Attendee::PERMISSIONS_DEFAULT
			->andWhere(
				$update->expr()->eq(
					$update->expr()->castColumn(
						$update->expr()->bitwiseAnd(
							'default_permissions',
							128 // Attendee::PERMISSIONS_CHAT
						),
						IQueryBuilder::PARAM_INT
					),
					$update->createNamedParameter(128, IQueryBuilder::PARAM_INT) // Attendee::PERMISSIONS_CHAT
				)
			)
			->andWhere(
				$update->expr()->neq(
					$update->expr()->castColumn(
						$update->expr()->bitwiseAnd(
							'default_permissions',
							256 // Attendee::PERMISSIONS_REACT
						),
						IQueryBuilder::PARAM_INT
					),
					$update->createNamedParameter(256, IQueryBuilder::PARAM_INT) // Attendee::PERMISSIONS_REACT - don't add if already set
				)
			);
		$update->executeStatement();

		// Add REACT permission (256) to all attendee permissions that have CHAT (128) set
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_attendees')
			->set('permissions', $update->func()->add(
				'permissions',
				$update->createNamedParameter(256, IQueryBuilder::PARAM_INT) // Attendee::PERMISSIONS_REACT
			))
			->where($update->expr()->neq('permissions', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))) // Attendee::PERMISSIONS_DEFAULT
			->andWhere(
				$update->expr()->eq(
					$update->expr()->castColumn(
						$update->expr()->bitwiseAnd(
							'permissions',
							128 // Attendee::PERMISSIONS_CHAT
						),
						IQueryBuilder::PARAM_INT
					),
					$update->createNamedParameter(128, IQueryBuilder::PARAM_INT) // Attendee::PERMISSIONS_CHAT
				)
			)
			->andWhere(
				$update->expr()->neq(
					$update->expr()->castColumn(
						$update->expr()->bitwiseAnd(
							'permissions',
							256 // Attendee::PERMISSIONS_REACT
						),
						IQueryBuilder::PARAM_INT
					),
					$update->createNamedParameter(256, IQueryBuilder::PARAM_INT) // Attendee::PERMISSIONS_REACT - don't add if already set
				)
			);
		$update->executeStatement();
	}
}

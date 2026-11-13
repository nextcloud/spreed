<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCA\Talk\RoomAttributes;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version26000Date20261110120000 extends SimpleMigrationStep {
	public function __construct(
		protected IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_rooms')
			// RoomAttributes::HIDDEN_PARTICIPANTS->value is 32
			->set('attributes', $update->expr()->bitwiseOr('attributes', 32))
			->where($update->expr()->eq(
				// RoomAttributes::CHANNEL->value is 8
				$update->expr()->bitwiseAnd('attributes', 8),
				$update->createNamedParameter(8, IQueryBuilder::PARAM_INT),
			));
		$update->executeStatement();
	}
}

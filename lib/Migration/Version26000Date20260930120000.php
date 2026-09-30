<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCA\Talk\Share\RoomShareProvider;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Copy the password of conversations to their existing file shares
 */
class Version26000Date20260930120000 extends SimpleMigrationStep {
	public function __construct(
		protected IDBConnection $connection,
		protected RoomShareProvider $roomShareProvider,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->select('token', 'password')
			->from('talk_rooms')
			->where($query->expr()->nonEmptyString('password'));

		$result = $query->executeQuery();
		while ($row = $result->fetchAssociative()) {
			$this->roomShareProvider->setPasswordInRoom($row['token'], $row['password']);
		}
		$result->closeCursor();
	}
}

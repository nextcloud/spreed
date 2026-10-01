<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Share\RoomShareProvider;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\IDBConnection;

/**
 * Copy the password of conversations to their existing file shares
 */
class CopyRoomPasswordsToShares extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly IDBConnection $connection,
		private readonly RoomShareProvider $roomShareProvider,
	) {
		parent::__construct($time);
	}

	#[\Override]
	protected function run($argument): void {
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

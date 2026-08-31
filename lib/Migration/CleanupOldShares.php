<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Share\RoomShareProvider;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Share\IShare;

/**
 * Remove old @see RoomShareProvider::SHARE_TYPE_USERROOM shares when the user
 * is no longer part of the conversation
 */
class CleanupOldShares implements IRepairStep {

	public const SELECT_CHUNK_SIZE = 50_000;
	public function __construct(
		private readonly IDBConnection $connection,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Remove old user-room-shares';
	}

	#[\Override]
	public function run(IOutput $output): void {
		// Select SHARE_TYPE_USERROOM shares when the user is no longer an attendee in the room
		$query = $this->connection->getQueryBuilder();
		$query->select('su.id')
			->from('share', 'su')
			->leftJoin('su', 'share', 'sr', $query->expr()->andX(
				$query->expr()->eq('su.parent', 'sr.id'),
				$query->expr()->eq('sr.share_type', $query->createNamedParameter(IShare::TYPE_ROOM)),
			))
			->leftJoin('sr', 'talk_rooms', 'r', $query->expr()->eq('sr.share_with', 'r.token'))
			->leftJoin('r', 'talk_attendees', 'a', $query->expr()->andX(
				$query->expr()->eq('r.id', 'a.room_id'),
				$query->expr()->eq('su.share_with', 'a.actor_id'),
				$query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)),
			))
			->where($query->expr()->eq('su.share_type', $query->createNamedParameter(RoomShareProvider::SHARE_TYPE_USERROOM)))
			->andWhere($query->expr()->isNull('a.id'))
			->setMaxResults(self::SELECT_CHUNK_SIZE);

		// Deleting the SHARE_TYPE_USERROOM shares based on their ID
		$delete = $this->connection->getQueryBuilder();
		$delete->delete('share')
			->where($delete->expr()->eq('share_type', $delete->createNamedParameter(RoomShareProvider::SHARE_TYPE_USERROOM)))
			->andWhere($delete->expr()->in('id', $delete->createParameter('ids')));

		$output->startProgress();
		do {
			$numDeleted = $this->deleteSomeFormerShares($output, $query, $delete);
		} while ($numDeleted !== 0);
		$output->finishProgress();

		$output->info('Deleted ' . $numDeleted . ' stray shares');
	}

	protected function deleteSomeFormerShares(IOutput $output, IQueryBuilder $select, IQueryBuilder $delete): int {
		$result = $select->executeQuery();
		$ids = [];
		while ($row = $result->fetchAssociative()) {
			$ids[] = (int)$row['id'];
		}
		$result->closeCursor();

		if (empty($ids)) {
			return 0;
		}

		$count = 0;
		$chunks = array_chunk($ids, 1000);
		foreach ($chunks as $chunk) {
			$delete->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$delete->executeStatement();
			$output->advance(count($chunk));
			$count += count($chunk);
		}
		return $count;
	}
}

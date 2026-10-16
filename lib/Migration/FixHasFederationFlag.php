<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Make sure the has_federation flag of conversations matches the existence of
 * federated users in the attendees table
 */
class FixHasFederationFlag implements IRepairStep {
	public function __construct(
		private readonly IDBConnection $connection,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Fix the federation flag of conversations';
	}

	#[\Override]
	public function run(IOutput $output): void {
		// Conversations with federated users but without the flag
		$query = $this->connection->getQueryBuilder();
		$query->selectDistinct('r.id')
			->from('talk_rooms', 'r')
			->innerJoin('r', 'talk_attendees', 'a', $query->expr()->andX(
				$query->expr()->eq('r.id', 'a.room_id'),
				$query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_FEDERATED_USERS)),
			))
			->where($query->expr()->eq('r.has_federation', $query->createNamedParameter(Room::HAS_FEDERATION_NONE, IQueryBuilder::PARAM_INT)));
		$flagged = $this->updateFlag($query, Room::HAS_FEDERATION_TALKv1);

		// Conversations with the flag but without federated users
		$query = $this->connection->getQueryBuilder();
		$query->select('r.id')
			->from('talk_rooms', 'r')
			->leftJoin('r', 'talk_attendees', 'a', $query->expr()->andX(
				$query->expr()->eq('r.id', 'a.room_id'),
				$query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_FEDERATED_USERS)),
			))
			->where($query->expr()->neq('r.has_federation', $query->createNamedParameter(Room::HAS_FEDERATION_NONE, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->isNull('a.id'));
		$unflagged = $this->updateFlag($query, Room::HAS_FEDERATION_NONE);

		if ($flagged || $unflagged) {
			$output->info('Federation flag added to ' . $flagged . ' and removed from ' . $unflagged . ' conversations');
		}
	}

	/**
	 * @psalm-param int-mask-of<Room::HAS_FEDERATION_*> $hasFederation
	 */
	protected function updateFlag(IQueryBuilder $select, int $hasFederation): int {
		$result = $select->executeQuery();
		$ids = [];
		while ($row = $result->fetchAssociative()) {
			$ids[] = (int)$row['id'];
		}
		$result->closeCursor();

		$update = $this->connection->getQueryBuilder();
		$update->update('talk_rooms')
			->set('has_federation', $update->createNamedParameter($hasFederation, IQueryBuilder::PARAM_INT))
			->where($update->expr()->in('id', $update->createParameter('ids')));

		foreach (array_chunk($ids, IQueryBuilder::MAX_IN_PARAMETERS) as $chunk) {
			$update->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$update->executeStatement();
		}

		return count($ids);
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use OCA\Talk\Model\Attendee;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class CacheUserDisplayNames implements IRepairStep {

	public function __construct(
		protected IDBConnection $connection,
		protected IUserManager $userManager,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Cache the user display names';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_attendees')
			->set('display_name', $update->createParameter('displayName'))
			->where($update->expr()->eq('actor_type', $update->createParameter('actorType')))
			->andWhere($update->expr()->eq('actor_id', $update->createParameter('actorId')));

		$query = $this->connection->getQueryBuilder();
		$query->select('actor_id')
			->from('talk_attendees')
			->where($query->expr()->eq('actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)))
			->andWhere($query->expr()->eq('display_name', $query->createNamedParameter('')))
			->groupBy('actor_id');

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$user = $this->userManager->get($row['actor_id']);
			if (!$user instanceof IUser) {
				continue;
			}

			$update->setParameter('displayName', $user->getDisplayName())
				->setParameter('actorType', Attendee::ACTOR_USERS)
				->setParameter('actorId', $row['actor_id']);
			$update->executeStatement();
		}
		$result->closeCursor();
	}
}

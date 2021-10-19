<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Migration;

use OCA\Talk\Model\Attendee;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class CacheUserDisplayNames implements IRepairStep {

	/** @var IDBConnection */
	protected $connection;
	/** @var IUserManager */
	protected $userManager;

	public function __construct(IDBConnection $connection,
								IUserManager $userManager) {
		$this->connection = $connection;
		$this->userManager = $userManager;
	}

	public function getName(): string {
		return 'Cache the user display names';
	}

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

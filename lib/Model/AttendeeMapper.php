<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception as DBException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Attendee mapRowToEntity(array $row)
 * @method Attendee findEntity(IQueryBuilder $query)
 * @method Attendee[] findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Attendee>
 */
class AttendeeMapper extends QBMapper {
	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_attendees', Attendee::class);
	}

	/**
	 * @param int $roomId
	 * @param string $actorType
	 * @param string $actorId
	 * @return Attendee
	 * @throws DoesNotExistException
	 */
	public function findByActor(int $roomId, string $actorType, string $actorId): Attendee {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($roomId)));

		return $this->findEntity($query);
	}

	/**
	 * @param int $id
	 * @return Attendee
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws DBException
	 */
	public function getById(int $id): Attendee {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($query);
	}

	/**
	 * @param int $id
	 * @param string $token
	 * @return Attendee
	 * @throws DBException
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getByRemoteIdAndToken(int $id, string $token): Attendee {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('remote_id', $query->createNamedParameter($id, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('access_token', $query->createNamedParameter($token, IQueryBuilder::PARAM_STR)));

		return $this->findEntity($query);
	}

	/**
	 * @param int $roomId
	 * @param string $actorType
	 * @param int|null $lastJoinedCall
	 * @return Attendee[]
	 */
	public function getActorsByType(int $roomId, string $actorType, ?int $lastJoinedCall = null): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)));

		if ($lastJoinedCall !== null) {
			$query->andWhere($query->expr()->gte('last_joined_call', $query->createNamedParameter($lastJoinedCall, IQueryBuilder::PARAM_INT)));
		}

		return $this->findEntities($query);
	}

	/**
	 * @param int $roomId
	 * @param array $participantType
	 * @return Attendee[]
	 * @throws DBException
	 */
	public function getActorsByParticipantTypes(int $roomId, array $participantType): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		if (!empty($participantType)) {
			$query->andWhere($query->expr()->in('participant_type', $query->createNamedParameter($participantType, IQueryBuilder::PARAM_INT_ARRAY)));
		}

		return $this->findEntities($query);
	}

	/**
	 * @param int $roomId
	 * @param string $actorType
	 * @param int|null $lastJoinedCall
	 * @return int
	 */
	public function getActorsCountByType(int $roomId, string $actorType, ?int $lastJoinedCall = null): int {
		$query = $this->db->getQueryBuilder();
		$query->select($query->func()->count('*', 'num_actors'))
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)));

		if ($lastJoinedCall !== null) {
			$query->andWhere($query->expr()->gte('last_joined_call', $query->createNamedParameter($lastJoinedCall, IQueryBuilder::PARAM_INT)));
		}

		$result = $query->executeQuery();
		$count = (int) $result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	/**
	 * @param int $roomId
	 * @param int[] $participantType
	 * @return int
	 */
	public function countActorsByParticipantType(int $roomId, array $participantType): int {
		$query = $this->db->getQueryBuilder();
		$query->select($query->func()->count('*', 'num_actors'))
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->notIn('actor_type', $query->createNamedParameter([
				Attendee::ACTOR_CIRCLES,
				Attendee::ACTOR_GROUPS,
			], IQueryBuilder::PARAM_STR_ARRAY)));

		if (!empty($participantType)) {
			$query->andWhere($query->expr()->in('participant_type', $query->createNamedParameter($participantType, IQueryBuilder::PARAM_INT_ARRAY)));
		}

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (int) ($row['num_actors'] ?? 0);
	}

	/**
	 * @param int[] $ids
	 * @return int Number of deleted entities
	 */
	public function deleteByIds(array $ids): int {
		$delete = $this->db->getQueryBuilder();
		$delete->delete($this->getTableName())
			->where($delete->expr()->in('id', $delete->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));

		return $delete->executeStatement();
	}

	public function modifyPermissions(int $roomId, string $mode, int $newState): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->notIn('actor_type', $query->createNamedParameter([
				Attendee::ACTOR_CIRCLES,
				Attendee::ACTOR_GROUPS,
			], IQueryBuilder::PARAM_STR_ARRAY)));

		if ($mode === Attendee::PERMISSIONS_MODIFY_SET) {
			if ($newState !== Attendee::PERMISSIONS_DEFAULT) {
				$newState |= Attendee::PERMISSIONS_CUSTOM;
			}
			$query->set('permissions', $query->createNamedParameter($newState, IQueryBuilder::PARAM_INT));
			$query->executeStatement();
		} else {
			foreach ([
				Attendee::PERMISSIONS_CALL_JOIN,
				Attendee::PERMISSIONS_CALL_START,
				Attendee::PERMISSIONS_PUBLISH_AUDIO,
				Attendee::PERMISSIONS_PUBLISH_VIDEO,
				Attendee::PERMISSIONS_PUBLISH_SCREEN,
				Attendee::PERMISSIONS_LOBBY_IGNORE,
			] as $permission) {
				if ($permission & $newState) {
					if ($mode === Attendee::PERMISSIONS_MODIFY_ADD) {
						$this->addSinglePermission($query, $permission);
					} elseif ($mode === Attendee::PERMISSIONS_MODIFY_REMOVE) {
						$this->removeSinglePermission($query, $permission);
					}
				}
			}
		}
	}

	protected function addSinglePermission(IQueryBuilder $query, int $permission): void {
		$query->set('permissions', $query->func()->add(
			'permissions',
			$query->createNamedParameter($permission, IQueryBuilder::PARAM_INT)
		));

		$query->andWhere(
			$query->expr()->neq(
				$query->expr()->castColumn(
					$query->expr()->bitwiseAnd(
						'permissions',
						$permission
					),
					IQueryBuilder::PARAM_INT
				),
				$query->createNamedParameter($permission, IQueryBuilder::PARAM_INT)
			)
		);

		$query->executeStatement();
	}

	protected function removeSinglePermission(IQueryBuilder $query, int $permission): void {
		$query->set('permissions', $query->func()->subtract(
			'permissions',
			$query->createNamedParameter($permission, IQueryBuilder::PARAM_INT)
		));

		$query->andWhere(
			$query->expr()->eq(
				$query->expr()->castColumn(
					$query->expr()->bitwiseAnd(
						'permissions',
						$permission
					),
					IQueryBuilder::PARAM_INT
				),
				$query->createNamedParameter($permission, IQueryBuilder::PARAM_INT)
			)
		);

		$query->executeStatement();
	}

	public function createAttendeeFromRow(array $row): Attendee {
		return $this->mapRowToEntity([
			'id' => $row['a_id'],
			'room_id' => $row['room_id'],
			'actor_type' => $row['actor_type'],
			'actor_id' => $row['actor_id'],
			'display_name' => (string) $row['display_name'],
			'pin' => $row['pin'],
			'participant_type' => (int) $row['participant_type'],
			'favorite' => (bool) $row['favorite'],
			'notification_level' => (int) $row['notification_level'],
			'notification_calls' => (int) $row['notification_calls'],
			'last_joined_call' => (int) $row['last_joined_call'],
			'last_read_message' => (int) $row['last_read_message'],
			'last_mention_message' => (int) $row['last_mention_message'],
			'last_mention_direct' => (int) $row['last_mention_direct'],
			'read_privacy' => (int) $row['read_privacy'],
			'permissions' => (int) $row['permissions'],
			'access_token' => (string) $row['access_token'],
			'remote_id' => (string) $row['remote_id'],
			'phone_number' => (string) $row['phone_number'],
		]);
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
 * @method list<Attendee> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Attendee>
 */
class AttendeeMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_attendees', Attendee::class);
	}

	/**
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
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getById(int $id): Attendee {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($query);
	}

	/**
	 * @return list<Attendee>
	 */
	public function getByAccessToken(string $accessToken): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('access_token', $query->createNamedParameter($accessToken)));
		// There could be multiple in case of local federation,
		// so we have to get all and afterwards check
		// the actor id for the serverUrl.
		return $this->findEntities($query);
	}

	/**
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
	 * @return list<Attendee>
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
	 * @return list<Attendee>
	 */
	public function getActorsByTypes(int $roomId, array $actorTypes, ?int $lastJoinedCall = null): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->in('actor_type', $query->createNamedParameter($actorTypes, IQueryBuilder::PARAM_STR_ARRAY)));

		if ($lastJoinedCall !== null) {
			$query->andWhere($query->expr()->gte('last_joined_call', $query->createNamedParameter($lastJoinedCall, IQueryBuilder::PARAM_INT)));
		}

		return $this->findEntities($query);
	}

	/**
	 * @return list<Attendee>
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
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	/**
	 * @param int[] $participantType
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

		return (int)($row['num_actors'] ?? 0);
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
		if ($mode === Attendee::PERMISSIONS_MODIFY_SET) {
			if ($newState !== Attendee::PERMISSIONS_DEFAULT) {
				$newState |= Attendee::PERMISSIONS_CUSTOM;
			}

			$query = $this->getModifyPermissionsBaseQuery($roomId);
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
					$query = $this->getModifyPermissionsBaseQuery($roomId);

					if ($mode === Attendee::PERMISSIONS_MODIFY_ADD) {
						$this->addSinglePermission($query, $permission);
					} elseif ($mode === Attendee::PERMISSIONS_MODIFY_REMOVE) {
						$this->removeSinglePermission($query, $permission);
					}
				}
			}
		}
	}

	protected function getModifyPermissionsBaseQuery(int $roomId): IQueryBuilder {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->notIn('actor_type', $query->createNamedParameter([
				Attendee::ACTOR_CIRCLES,
				Attendee::ACTOR_GROUPS,
			], IQueryBuilder::PARAM_STR_ARRAY)));

		return $query;
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
		$query->andWhere(
			$query->expr()->neq(
				'permissions',
				$query->createNamedParameter(Attendee::PERMISSIONS_DEFAULT, IQueryBuilder::PARAM_INT)
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
		// Removing permissions does not need to be explicitly prevented when
		// the attendee has default permissions, as in that case it will not be
		// possible to remove the permissions anyway.

		$query->executeStatement();
	}

	public function createAttendeeFromRow(array $row): Attendee {
		return $this->mapRowToEntity([
			'id' => $row['a_id'],
			'room_id' => $row['room_id'],
			'actor_type' => $row['actor_type'],
			'actor_id' => $row['actor_id'],
			'display_name' => (string)$row['display_name'],
			'pin' => $row['pin'],
			'participant_type' => (int)$row['participant_type'],
			'favorite' => (bool)$row['favorite'],
			'notification_level' => (int)$row['notification_level'],
			'notification_calls' => (int)$row['notification_calls'],
			'last_joined_call' => (int)$row['last_joined_call'],
			'last_read_message' => (int)$row['last_read_message'],
			'last_mention_message' => (int)$row['last_mention_message'],
			'last_mention_direct' => (int)$row['last_mention_direct'],
			'read_privacy' => (int)$row['read_privacy'],
			'permissions' => (int)$row['permissions'],
			'access_token' => (string)$row['access_token'],
			'remote_id' => (string)$row['remote_id'],
			'invited_cloud_id' => (string)$row['invited_cloud_id'],
			'phone_number' => $row['phone_number'],
			'call_id' => $row['call_id'],
			'state' => (int)$row['state'],
			'unread_messages' => (int)$row['unread_messages'],
			'last_attendee_activity' => (int)$row['last_attendee_activity'],
			'archived' => (bool)$row['archived'],
			'important' => (bool)$row['important'],
			'sensitive' => (bool)$row['sensitive'],
			'has_unread_threads' => (bool)$row['has_unread_threads'],
			'has_unread_thread_mentions' => (bool)$row['has_unread_thread_mentions'],
			'has_unread_thread_directs' => (bool)$row['has_unread_thread_directs'],
		]);
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUser;
use SensitiveParameter;

/**
 * Class InvitationMapper
 *
 * @package OCA\Talk\Model
 *
 * @method Invitation mapRowToEntity(array $row)
 * @method Invitation findEntity(IQueryBuilder $query)
 * @method list<Invitation> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Invitation>
 */
class InvitationMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_invitations', Invitation::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getInvitationById(int $id): Invitation {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @internal Does not check user relation
	 */
	public function getByRemoteServerAndAccessToken(
		string $remoteServerUrl,
		#[SensitiveParameter]
		string $accessToken,
	): Invitation {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('remote_server_url', $qb->createNamedParameter($remoteServerUrl)))
			->andWhere($qb->expr()->eq('access_token', $qb->createNamedParameter($accessToken)));

		return $this->findEntity($qb);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getByRemoteAndAccessToken(
		string $remoteServerUrl,
		int $remoteAttendeeId,
		#[SensitiveParameter]
		string $accessToken,
	): Invitation {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('remote_server_url', $qb->createNamedParameter($remoteServerUrl)))
			->andWhere($qb->expr()->eq('remote_attendee_id', $qb->createNamedParameter($remoteAttendeeId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('access_token', $qb->createNamedParameter($accessToken)));

		return $this->findEntity($qb);
	}

	/**
	 * @param IUser $user
	 * @return list<Invitation>
	 */
	public function getInvitationsForUser(IUser $user): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($user->getUID())));

		return $this->findEntities($qb);
	}

	/**
	 * @psalm-param Invitation::STATE_*|null $state
	 */
	public function countInvitationsForUser(IUser $user, ?int $state = null): int {
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->func()->count('*'))
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($user->getUID())));

		if ($state !== null) {
			$qb->andWhere($qb->expr()->eq('state', $qb->createNamedParameter($state)));
		}

		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getInvitationForUserByLocalRoom(Room $room, string $userId, bool $caseInsensitive = false): Invitation {
		$query = $this->db->getQueryBuilder();

		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('local_room_id', $query->createNamedParameter($room->getId())));

		if ($caseInsensitive) {
			$query->andWhere($query->expr()->eq($query->func()->lower('user_id'), $query->createNamedParameter(strtolower($userId))));
		} else {
			$query->andWhere($query->expr()->eq('user_id', $query->createNamedParameter($userId)));
		}
		return $this->findEntity($query);
	}

	public function countInvitationsForLocalRoom(Room $room): int {
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->func()->count('*', 'num_invitations'))
			->from($this->getTableName())
			->where($qb->expr()->eq('local_room_id', $qb->createNamedParameter($room->getId())));

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (int)($row['num_invitations'] ?? 0);
	}
}

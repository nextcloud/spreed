<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<ImportRef>
 */
class ImportRefMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_import_refs', ImportRef::class);
	}

	/**
	 * @param ImportRef::KIND_* $kind
	 * @throws DoesNotExistException
	 */
	public function findByRef(string $app, string $kind, int $roomId, string $ref): ImportRef {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('kind', $query->createNamedParameter($kind)))
			->andWhere($query->expr()->eq('ref_hash', $query->createNamedParameter(ImportRef::hashRef($kind, $roomId, $ref))))
			->andWhere($query->expr()->eq('app', $query->createNamedParameter($app)));

		return $this->findEntity($query);
	}

	/**
	 * @param ImportRef::KIND_* $kind
	 * @param list<string> $refs
	 * @return array<string, ImportRef> keyed by ref
	 */
	public function findByRefs(string $app, string $kind, int $roomId, array $refs): array {
		$hashes = [];
		foreach ($refs as $ref) {
			$hashes[ImportRef::hashRef($kind, $roomId, $ref)] = true;
		}
		if ($hashes === []) {
			return [];
		}

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('kind', $query->createNamedParameter($kind)))
			->andWhere($query->expr()->in('ref_hash', $query->createParameter('hashes')))
			->andWhere($query->expr()->eq('app', $query->createNamedParameter($app)));

		$found = [];
		foreach (array_chunk(array_keys($hashes), IQueryBuilder::MAX_IN_PARAMETERS) as $chunk) {
			$query->setParameter('hashes', $chunk, IQueryBuilder::PARAM_STR_ARRAY);
			foreach ($this->findEntities($query) as $entity) {
				$found[(string)$entity->getRef()] = $entity;
			}
		}
		return $found;
	}

	/**
	 * The ref of the app's conversation with the room ID
	 *
	 * @throws DoesNotExistException
	 */
	public function findRoom(string $app, int $roomId): ImportRef {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('kind', $query->createNamedParameter(ImportRef::KIND_ROOM)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('app', $query->createNamedParameter($app)));

		return $this->findEntity($query);
	}

	/**
	 * @param ImportRef::KIND_* $kind
	 * @return list<ImportRef>
	 */
	public function findAllInRoom(string $app, string $kind, int $roomId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('kind', $query->createNamedParameter($kind)))
			->andWhere($query->expr()->eq('app', $query->createNamedParameter($app)));

		return $this->findEntities($query);
	}

	/**
	 * Message refs of the room by message ID, for paging
	 *
	 * @return list<ImportRef>
	 */
	public function findMessagesAfter(string $app, int $roomId, int $afterMessageId, int $limit): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('kind', $query->createNamedParameter(ImportRef::KIND_MESSAGE)))
			->andWhere($query->expr()->gt('object_id', $query->createNamedParameter($afterMessageId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('state', $query->createNamedParameter(ImportRef::STATE_ACTIVE, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('app', $query->createNamedParameter($app)))
			->orderBy('object_id', 'ASC')
			->setMaxResults($limit);

		return $this->findEntities($query);
	}

	/**
	 * Message refs of the given message IDs in the room, keyed by message ID
	 *
	 * @param list<int> $messageIds
	 * @return array<int, ImportRef>
	 */
	public function findMessagesByIds(int $roomId, array $messageIds): array {
		if ($messageIds === []) {
			return [];
		}
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('kind', $query->createNamedParameter(ImportRef::KIND_MESSAGE)))
			->andWhere($query->expr()->in('object_id', $query->createParameter('ids')));

		$found = [];
		foreach (array_chunk(array_values(array_unique($messageIds)), IQueryBuilder::MAX_IN_PARAMETERS) as $chunk) {
			$query->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			foreach ($this->findEntities($query) as $entity) {
				$found[$entity->getObjectId()] = $entity;
			}
		}
		return $found;
	}

	/**
	 * Display names of placeholder actors by their ID, from any importing app
	 *
	 * @param list<string> $actorIds
	 * @return array<string, string>
	 */
	public function getActorDisplayNames(array $actorIds): array {
		$hashes = [];
		foreach ($actorIds as $actorId) {
			$hashes[ImportRef::hashRef(ImportRef::KIND_ACTOR, 0, $actorId)] = true;
		}
		if ($hashes === []) {
			return [];
		}

		$query = $this->db->getQueryBuilder();
		$query->select('ref', 'display_name')
			->from($this->getTableName())
			->where($query->expr()->eq('kind', $query->createNamedParameter(ImportRef::KIND_ACTOR)))
			->andWhere($query->expr()->in('ref_hash', $query->createParameter('hashes')));

		$names = [];
		foreach (array_chunk(array_keys($hashes), IQueryBuilder::MAX_IN_PARAMETERS) as $chunk) {
			$query->setParameter('hashes', $chunk, IQueryBuilder::PARAM_STR_ARRAY);
			$result = $query->executeQuery();
			while ($row = $result->fetchAssociative()) {
				$names[(string)$row['ref']] = (string)$row['display_name'];
			}
			$result->closeCursor();
		}
		return $names;
	}
}

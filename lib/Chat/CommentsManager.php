<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat;

use OC\Comments\Comment;
use OC\Comments\Manager;
use OCP\Comments\IComment;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;

class CommentsManager extends Manager {
	public function removeFromCache(int|string $id): void {
		$this->uncache((string)$id);
	}

	/**
	 * @param array $data
	 * @return IComment
	 */
	public function getCommentFromData(array $data): IComment {
		$message = $data['message'];
		unset($data['message']);
		$comment = new Comment($this->normalizeDatabaseData($data));
		$comment->setMessage($message, ChatManager::MAX_CHAT_LENGTH);
		return $comment;
	}

	/**
	 * @param string[] $ids
	 * @return IComment[]
	 * @throws Exception
	 */
	public function getCommentsById(array $ids): array {
		$commentIds = array_map(intval(...), $ids);

		$query = $this->dbConn->getQueryBuilder();
		$query->select('*')
			->from('comments')
			->where($query->expr()->in('id', $query->createNamedParameter($commentIds, IQueryBuilder::PARAM_INT_ARRAY)));

		$comments = [];
		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$comments[(int)$row['id']] = $this->getCommentFromData($row);
		}
		$result->closeCursor();

		return $comments;
	}

	/**
	 * FIXME: TEMPORARY method until https://github.com/nextcloud/server/pull/53896 is merged
	 *
	 * @param string $objectType the object type, e.g. 'files'
	 * @param string $objectId the id of the object
	 * @param int $lastKnownCommentId the last known comment (will be used as offset)
	 * @param string $sortDirection direction of the comments (`asc` or `desc`)
	 * @param int $limit optional, number of maximum comments to be returned. if
	 *                   set to 0, all comments are returned.
	 * @param bool $includeLastKnown
	 * @param ?\DateTimeInterface $notBefore only comments created at or after this time
	 * @return list<IComment>
	 */
	#[\Override]
	public function getForObjectSince(
		string $objectType,
		string $objectId,
		int $lastKnownCommentId,
		string $sortDirection = 'asc',
		int $limit = 30,
		bool $includeLastKnown = false,
		string $topmostParentId = '',
		?\DateTimeInterface $notBefore = null,
	): array {
		return $this->getCommentsWithVerbForObjectSinceComment(
			$objectType,
			$objectId,
			[],
			$lastKnownCommentId,
			$sortDirection,
			$limit,
			$includeLastKnown,
			$topmostParentId,
			$notBefore,
		);
	}

	/**
	 * FIXME: TEMPORARY method until https://github.com/nextcloud/server/pull/53896 is merged
	 *
	 * @param string $objectType the object type, e.g. 'files'
	 * @param string $objectId the id of the object
	 * @param string[] $verbs List of verbs to filter by
	 * @param int $lastKnownCommentId the last known comment (will be used as offset)
	 * @param string $sortDirection direction of the comments (`asc` or `desc`)
	 * @param int $limit optional, number of maximum comments to be returned. if
	 *                   set to 0, all comments are returned.
	 * @param bool $includeLastKnown
	 * @param ?\DateTimeInterface $notBefore only comments created at or after this time
	 * @return list<IComment>
	 */
	#[\Override]
	public function getCommentsWithVerbForObjectSinceComment(
		string $objectType,
		string $objectId,
		array $verbs,
		int $lastKnownCommentId,
		string $sortDirection = 'asc',
		int $limit = 30,
		bool $includeLastKnown = false,
		string $topmostParentId = '',
		?\DateTimeInterface $notBefore = null,
	): array {
		$comments = [];

		$query = $this->dbConn->getQueryBuilder();
		$query->select('*')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($objectId)))
			->orderBy('creation_timestamp', $sortDirection === 'desc' ? 'DESC' : 'ASC')
			->addOrderBy('id', $sortDirection === 'desc' ? 'DESC' : 'ASC');

		if ($limit > 0) {
			$query->setMaxResults($limit);
		}

		if (!empty($verbs)) {
			$query->andWhere($query->expr()->in('verb', $query->createNamedParameter($verbs, IQueryBuilder::PARAM_STR_ARRAY)));
		}

		if ($topmostParentId !== '') {
			$query->andWhere($query->expr()->orX(
				$query->expr()->eq('id', $query->createNamedParameter($topmostParentId)),
				$query->expr()->eq('topmost_parent_id', $query->createNamedParameter($topmostParentId)),
			));
		}

		if ($notBefore !== null) {
			$query->andWhere($query->expr()->gte(
				'creation_timestamp',
				$query->createNamedParameter(\DateTime::createFromInterface($notBefore), IQueryBuilder::PARAM_DATETIME_MUTABLE),
				IQueryBuilder::PARAM_DATETIME_MUTABLE
			));
		}

		$lastKnownComment = $lastKnownCommentId > 0 ? $this->getLastKnownComment(
			$objectType,
			$objectId,
			$lastKnownCommentId
		) : null;
		if ($lastKnownComment instanceof IComment) {
			$lastKnownCommentDateTime = $lastKnownComment->getCreationDateTime();
			if ($sortDirection === 'desc') {
				if ($includeLastKnown) {
					$idComparison = $query->expr()->lte('id', $query->createNamedParameter($lastKnownCommentId));
				} else {
					$idComparison = $query->expr()->lt('id', $query->createNamedParameter($lastKnownCommentId));
				}
				$query->andWhere(
					$query->expr()->orX(
						$query->expr()->lt(
							'creation_timestamp',
							$query->createNamedParameter($lastKnownCommentDateTime, IQueryBuilder::PARAM_DATETIME_MUTABLE),
							IQueryBuilder::PARAM_DATETIME_MUTABLE
						),
						$query->expr()->andX(
							$query->expr()->eq(
								'creation_timestamp',
								$query->createNamedParameter($lastKnownCommentDateTime, IQueryBuilder::PARAM_DATETIME_MUTABLE),
								IQueryBuilder::PARAM_DATETIME_MUTABLE
							),
							$idComparison
						)
					)
				);
			} else {
				if ($includeLastKnown) {
					$idComparison = $query->expr()->gte('id', $query->createNamedParameter($lastKnownCommentId));
				} else {
					$idComparison = $query->expr()->gt('id', $query->createNamedParameter($lastKnownCommentId));
				}
				$query->andWhere(
					$query->expr()->orX(
						$query->expr()->gt(
							'creation_timestamp',
							$query->createNamedParameter($lastKnownCommentDateTime, IQueryBuilder::PARAM_DATETIME_MUTABLE),
							IQueryBuilder::PARAM_DATETIME_MUTABLE
						),
						$query->expr()->andX(
							$query->expr()->eq(
								'creation_timestamp',
								$query->createNamedParameter($lastKnownCommentDateTime, IQueryBuilder::PARAM_DATETIME_MUTABLE),
								IQueryBuilder::PARAM_DATETIME_MUTABLE
							),
							$idComparison
						)
					)
				);
			}
		} elseif ($lastKnownCommentId > 0) {
			// We didn't find the "$lastKnownComment" but we still use the ID as an offset.
			// This is required as a fall-back for expired messages in talk and deleted comments in other apps.
			if ($sortDirection === 'desc') {
				if ($includeLastKnown) {
					$query->andWhere($query->expr()->lte('id', $query->createNamedParameter($lastKnownCommentId)));
				} else {
					$query->andWhere($query->expr()->lt('id', $query->createNamedParameter($lastKnownCommentId)));
				}
			} else {
				if ($includeLastKnown) {
					$query->andWhere($query->expr()->gte('id', $query->createNamedParameter($lastKnownCommentId)));
				} else {
					$query->andWhere($query->expr()->gt('id', $query->createNamedParameter($lastKnownCommentId)));
				}
			}
		}

		$resultStatement = $query->executeQuery();
		while ($data = $resultStatement->fetch()) {
			$comment = $this->getCommentFromData($data);
			$this->cache($comment);
			$comments[] = $comment;
		}
		$resultStatement->closeCursor();

		return $comments;
	}

	/**
	 * @param string $actorType
	 * @param string $actorId
	 * @param string[] $messageIds
	 * @return array
	 * @psalm-return array<int, string[]>
	 */
	public function retrieveReactionsByActor(string $actorType, string $actorId, array $messageIds): array {
		$commentIds = array_map(intval(...), $messageIds);

		$query = $this->dbConn->getQueryBuilder();
		$query->select('*')
			->from('reactions')
			->where($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId)))
			->andWhere($query->expr()->in('parent_id', $query->createNamedParameter($commentIds, IQueryBuilder::PARAM_INT_ARRAY)));

		$reactions = [];
		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$reactions[(int)$row['parent_id']] ??= [];
			$reactions[(int)$row['parent_id']][] = $row['reaction'];
		}
		$result->closeCursor();

		return $reactions;
	}

	/**
	 * Search for comments on one or more objects with a given content
	 *
	 * @param string $search content to search for
	 * @param string $objectType Limit the search by object type
	 * @param string[] $objectIds Limit the search by object ids
	 * @param string[] $verbs Limit the verb of the comment
	 * @param array<string, \DateTimeInterface> $notBeforeByObjectId Comments of these objects only from the given time on
	 * @return list<IComment>
	 */
	public function searchForObjectsWithFilters(string $search, string $objectType, array $objectIds, array $verbs, ?\DateTimeImmutable $since, ?\DateTimeImmutable $until, ?string $actorType, ?string $actorId, int $offset, int $limit = 50, array $notBeforeByObjectId = []): array {
		$query = $this->dbConn->getQueryBuilder();

		$query->select('*')
			->from('comments')
			->orderBy('creation_timestamp', 'DESC')
			->addOrderBy('id', 'DESC')
			->setMaxResults($limit);

		if ($search !== '') {
			$query->where($query->expr()->iLike('message', $query->createNamedParameter(
				'%' . $this->dbConn->escapeLikeParameter($search) . '%'
			)));
		}

		if ($since !== null) {
			$query->andWhere($query->expr()->gte('creation_timestamp', $query->createNamedParameter($since, IQueryBuilder::PARAM_DATETIME_MUTABLE), IQueryBuilder::PARAM_DATETIME_MUTABLE));
		}

		if ($until !== null) {
			$query->andWhere($query->expr()->lte('creation_timestamp', $query->createNamedParameter($until, IQueryBuilder::PARAM_DATETIME_MUTABLE), IQueryBuilder::PARAM_DATETIME_MUTABLE));
		}

		if ($actorType !== null && $actorId !== null) {
			$query->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)))
				->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId)));
		}

		if ($objectType !== '') {
			$query->andWhere($query->expr()->eq('object_type', $query->createNamedParameter($objectType)));
		}
		if (!empty($notBeforeByObjectId) && !empty($objectIds)) {
			$objectIds = array_map(strval(...), $objectIds);
			$objectConditions = [];
			$unrestricted = array_values(array_diff($objectIds, array_map(strval(...), array_keys($notBeforeByObjectId))));
			if (!empty($unrestricted)) {
				$objectConditions[] = $query->expr()->in('object_id', $query->createNamedParameter($unrestricted, IQueryBuilder::PARAM_STR_ARRAY));
			}
			foreach ($notBeforeByObjectId as $objectId => $notBefore) {
				if (!in_array((string)$objectId, $objectIds, true)) {
					continue;
				}
				$objectConditions[] = $query->expr()->andX(
					$query->expr()->eq('object_id', $query->createNamedParameter((string)$objectId)),
					$query->expr()->gte('creation_timestamp', $query->createNamedParameter(\DateTime::createFromInterface($notBefore), IQueryBuilder::PARAM_DATETIME_MUTABLE), IQueryBuilder::PARAM_DATETIME_MUTABLE),
				);
			}
			$query->andWhere($query->expr()->orX(...$objectConditions));
		} elseif (!empty($objectIds)) {
			$query->andWhere($query->expr()->in('object_id', $query->createNamedParameter($objectIds, IQueryBuilder::PARAM_STR_ARRAY)));
		}
		if (!empty($verbs)) {
			$query->andWhere($query->expr()->in('verb', $query->createNamedParameter($verbs, IQueryBuilder::PARAM_STR_ARRAY)));
		}
		if ($offset !== 0) {
			$query->setFirstResult($offset);
		}

		$comments = [];
		$result = $query->executeQuery();
		while ($data = $result->fetch()) {
			$comment = $this->getCommentFromData($data);
			$this->cache($comment);
			$comments[] = $comment;
		}
		$result->closeCursor();

		return $comments;
	}

	/**
	 * Inserts a comment of a history import with the values it carries
	 * (parent, topmost parent, creation time), without the comment events
	 * and without updating the parent's child information; the importer
	 * updates that once per batch ({@see self::updateChildrenInformationOf()}).
	 */
	public function insertImported(IComment $comment): void {
		$query = $this->dbConn->getQueryBuilder();
		$query->insert('comments')
			->values([
				'parent_id' => $query->createNamedParameter($comment->getParentId()),
				'topmost_parent_id' => $query->createNamedParameter($comment->getTopmostParentId()),
				'children_count' => $query->createNamedParameter($comment->getChildrenCount(), IQueryBuilder::PARAM_INT),
				'actor_type' => $query->createNamedParameter($comment->getActorType()),
				'actor_id' => $query->createNamedParameter($comment->getActorId()),
				'message' => $query->createNamedParameter($comment->getMessage()),
				'verb' => $query->createNamedParameter($comment->getVerb()),
				'creation_timestamp' => $query->createNamedParameter($comment->getCreationDateTime(), IQueryBuilder::PARAM_DATETIME_MUTABLE),
				'latest_child_timestamp' => $query->createNamedParameter($comment->getLatestChildDateTime(), IQueryBuilder::PARAM_DATETIME_MUTABLE),
				'object_type' => $query->createNamedParameter($comment->getObjectType()),
				'object_id' => $query->createNamedParameter($comment->getObjectId()),
				'expire_date' => $query->createNamedParameter($comment->getExpireDate(), IQueryBuilder::PARAM_DATETIME_MUTABLE),
				'reference_id' => $query->createNamedParameter($comment->getReferenceId()),
				'meta_data' => $query->createNamedParameter(json_encode($comment->getMetaData(), JSON_THROW_ON_ERROR)),
			]);
		$query->executeStatement();
		$comment->setId((string)$query->getLastInsertId());
	}

	/**
	 * Updates a comment of a history import without the comment events
	 */
	public function updateImported(IComment $comment): void {
		$query = $this->dbConn->getQueryBuilder();
		$query->update('comments')
			->set('parent_id', $query->createNamedParameter($comment->getParentId()))
			->set('topmost_parent_id', $query->createNamedParameter($comment->getTopmostParentId()))
			->set('actor_type', $query->createNamedParameter($comment->getActorType()))
			->set('actor_id', $query->createNamedParameter($comment->getActorId()))
			->set('message', $query->createNamedParameter($comment->getMessage()))
			->set('verb', $query->createNamedParameter($comment->getVerb()))
			->set('creation_timestamp', $query->createNamedParameter($comment->getCreationDateTime(), IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->set('meta_data', $query->createNamedParameter(json_encode($comment->getMetaData(), JSON_THROW_ON_ERROR)))
			->where($query->expr()->eq('id', $query->createNamedParameter((int)$comment->getId(), IQueryBuilder::PARAM_INT)));
		$query->executeStatement();
		$this->uncache($comment->getId());
	}

	/**
	 * Inserts the reaction row of an imported reaction comment
	 * ({@see self::insertImported()} first); the summary of the parent is
	 * updated by {@see self::updateReactionSummary()}
	 */
	public function insertImportedReactionRow(IComment $reaction): void {
		$query = $this->dbConn->getQueryBuilder();
		$query->insert('reactions')
			->values([
				'parent_id' => $query->createNamedParameter((int)$reaction->getParentId(), IQueryBuilder::PARAM_INT),
				'message_id' => $query->createNamedParameter((int)$reaction->getId(), IQueryBuilder::PARAM_INT),
				'actor_type' => $query->createNamedParameter($reaction->getActorType()),
				'actor_id' => $query->createNamedParameter($reaction->getActorId()),
				'reaction' => $query->createNamedParameter($reaction->getMessage()),
			]);
		$query->executeStatement();
	}

	/**
	 * Deletes comments of a history import and their reaction rows, without
	 * the comment events
	 *
	 * @param list<int> $ids
	 */
	public function deleteImported(array $ids): void {
		$deleteReactions = $this->dbConn->getQueryBuilder();
		$deleteReactions->delete('reactions')
			->where($deleteReactions->expr()->in('message_id', $deleteReactions->createParameter('ids')));
		$deleteComments = $this->dbConn->getQueryBuilder();
		$deleteComments->delete('comments')
			->where($deleteComments->expr()->in('id', $deleteComments->createParameter('ids')));

		foreach (array_chunk($ids, IQueryBuilder::MAX_IN_PARAMETERS) as $chunk) {
			$deleteReactions->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$deleteReactions->executeStatement();
			$deleteComments->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$deleteComments->executeStatement();
			foreach ($chunk as $id) {
				$this->uncache((string)$id);
			}
		}
	}

	/**
	 * Recomputes the reaction summary of messages like the comments manager
	 * does after a reaction: counts per reaction, most used first, at most 20
	 *
	 * @param list<int> $parentIds
	 */
	public function updateReactionSummary(array $parentIds): void {
		if ($parentIds === []) {
			return;
		}

		$select = $this->dbConn->getQueryBuilder();
		$select->select('parent_id', 'reaction')
			->selectAlias($select->func()->count('id'), 'total')
			->from('reactions')
			->where($select->expr()->in('parent_id', $select->createParameter('ids')))
			->groupBy('parent_id', 'reaction');

		$update = $this->dbConn->getQueryBuilder();
		$update->update('comments')
			->set('reactions', $update->createParameter('reactions'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		foreach (array_chunk(array_values(array_unique($parentIds)), IQueryBuilder::MAX_IN_PARAMETERS) as $chunk) {
			$counts = array_fill_keys($chunk, []);
			$select->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$result = $select->executeQuery();
			while ($row = $result->fetchAssociative()) {
				$counts[(int)$row['parent_id']][(string)$row['reaction']] = (int)$row['total'];
			}
			$result->closeCursor();

			foreach ($counts as $parentId => $reactions) {
				uksort($reactions, static fn (string $a, string $b): int => ($reactions[$b] <=> $reactions[$a]) ?: strcmp($a, $b));
				$reactions = array_slice($reactions, 0, 20, true);
				$update->setParameter('reactions', $reactions === [] ? null : json_encode($reactions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $reactions === [] ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR);
				$update->setParameter('id', $parentId, IQueryBuilder::PARAM_INT);
				$update->executeStatement();
				$this->uncache((string)$parentId);
			}
		}
	}

	/**
	 * Recomputes the child count and newest child time of comments, which
	 * the comments manager keeps for every comment with replies or reactions
	 *
	 * @param list<int> $parentIds
	 */
	public function updateChildrenInformationOf(array $parentIds): void {
		if ($parentIds === []) {
			return;
		}

		$select = $this->dbConn->getQueryBuilder();
		$select->select('parent_id')
			->selectAlias($select->func()->count('id'), 'children')
			->selectAlias($select->func()->max('creation_timestamp'), 'latest')
			->from('comments')
			->where($select->expr()->in('parent_id', $select->createParameter('ids')))
			->groupBy('parent_id');

		$update = $this->dbConn->getQueryBuilder();
		$update->update('comments')
			->set('children_count', $update->createParameter('children'))
			->set('latest_child_timestamp', $update->createParameter('latest'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		foreach (array_chunk(array_values(array_unique($parentIds)), IQueryBuilder::MAX_IN_PARAMETERS) as $chunk) {
			$select->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$result = $select->executeQuery();
			$rows = $result->fetchAllAssociative();
			$result->closeCursor();
			foreach ($rows as $row) {
				$update->setParameter('children', (int)$row['children'], IQueryBuilder::PARAM_INT);
				$update->setParameter('latest', $row['latest'], $row['latest'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR);
				$update->setParameter('id', (int)$row['parent_id'], IQueryBuilder::PARAM_INT);
				$update->executeStatement();
				$this->uncache((string)$row['parent_id']);
			}
		}
	}
}

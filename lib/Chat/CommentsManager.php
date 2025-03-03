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
		$commentIds = array_map('intval', $ids);

		$query = $this->dbConn->getQueryBuilder();
		$query->select('*')
			->from('comments')
			->where($query->expr()->in('id', $query->createNamedParameter($commentIds, IQueryBuilder::PARAM_INT_ARRAY)));

		$comments = [];
		$result = $query->execute();
		while ($row = $result->fetch()) {
			$comments[(int)$row['id']] = $this->getCommentFromData($row);
		}
		$result->closeCursor();

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
		$commentIds = array_map('intval', $messageIds);

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
	 * @return list<IComment>
	 */
	public function searchForObjectsWithFilters(string $search, string $objectType, array $objectIds, array $verbs, ?\DateTimeImmutable $since, ?\DateTimeImmutable $until, ?string $actorType, ?string $actorId, int $offset, int $limit = 50): array {
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
			$query->andWhere($query->expr()->gte('creation_timestamp', $query->createNamedParameter($since, IQueryBuilder::PARAM_DATE), IQueryBuilder::PARAM_DATE));
		}

		if ($until !== null) {
			$query->andWhere($query->expr()->lte('creation_timestamp', $query->createNamedParameter($until, IQueryBuilder::PARAM_DATE), IQueryBuilder::PARAM_DATE));
		}

		if ($actorType !== null && $actorId !== null) {
			$query->andWhere($query->expr()->lte('actor_type', $query->createNamedParameter($actorType)))
				->andWhere($query->expr()->lte('actor_id', $query->createNamedParameter($actorId)));
		}

		if ($objectType !== '') {
			$query->andWhere($query->expr()->eq('object_type', $query->createNamedParameter($objectType)));
		}
		if (!empty($objectIds)) {
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
}

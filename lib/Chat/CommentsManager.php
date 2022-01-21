<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Chat;

use OC\Comments\Comment;
use OC\Comments\Manager;
use OCP\Comments\IComment;
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
	 * TODO Remove when moved to server
	 *
	 * @param string $objectType the object type, e.g. 'files'
	 * @param string $objectId the id of the object
	 * @param string[] $verbs List of verbs to filter by
	 * @param int $lastKnownCommentId the last known comment (will be used as offset)
	 * @param string $sortDirection direction of the comments (`asc` or `desc`)
	 * @param int $limit optional, number of maximum comments to be returned. if
	 * set to 0, all comments are returned.
	 * @param bool $includeLastKnown
	 * @return IComment[]
	 * @return array
	 */
	public function getCommentsWithVerbForObjectSinceComment(
		string $objectType,
		string $objectId,
		array $verbs,
		int $lastKnownCommentId,
		string $sortDirection = 'asc',
		int $limit = 30,
		bool $includeLastKnown = false
	): array {
		$comments = [];

		$query = $this->dbConn->getQueryBuilder();
		$query->select('*')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($objectId)))
			->andWhere($query->expr()->in('verb', $query->createNamedParameter($verbs, IQueryBuilder::PARAM_STR_ARRAY)))
			->orderBy('creation_timestamp', $sortDirection === 'desc' ? 'DESC' : 'ASC')
			->addOrderBy('id', $sortDirection === 'desc' ? 'DESC' : 'ASC');

		if ($limit > 0) {
			$query->setMaxResults($limit);
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
							$query->createNamedParameter($lastKnownCommentDateTime, IQueryBuilder::PARAM_DATE),
							IQueryBuilder::PARAM_DATE
						),
						$query->expr()->andX(
							$query->expr()->eq(
								'creation_timestamp',
								$query->createNamedParameter($lastKnownCommentDateTime, IQueryBuilder::PARAM_DATE),
								IQueryBuilder::PARAM_DATE
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
							$query->createNamedParameter($lastKnownCommentDateTime, IQueryBuilder::PARAM_DATE),
							IQueryBuilder::PARAM_DATE
						),
						$query->expr()->andX(
							$query->expr()->eq(
								'creation_timestamp',
								$query->createNamedParameter($lastKnownCommentDateTime, IQueryBuilder::PARAM_DATE),
								IQueryBuilder::PARAM_DATE
							),
							$idComparison
						)
					)
				);
			}
		}

		$resultStatement = $query->execute();
		while ($data = $resultStatement->fetch()) {
			$comment = $this->getCommentFromData($data);
			$this->cache($comment);
			$comments[] = $comment;
		}
		$resultStatement->closeCursor();

		return $comments;
	}
}

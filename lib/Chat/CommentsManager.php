<?php
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

namespace OCA\Spreed\Chat;


use OC\Comments\Comment;
use OC\Comments\Manager;
use OCP\Comments\IComment;
use OCP\DB\QueryBuilder\IQueryBuilder;

class CommentsManager extends Manager {

	/**
	 * @param string $objectType
	 * @param string $objectId
	 * @param int $lastKnownCommentId
	 * @param string $sortDirection
	 * @param int $limit
	 * @return array
	 */
	public function getForObjectSinceTalkVersion(
		$objectType,
		$objectId,
		$lastKnownCommentId,
		$sortDirection = 'asc',
		$limit = 30
	) {
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

		$lastKnownComment = $this->getLastKnownCommentTalkVersion(
			$objectType,
			$objectId,
			$lastKnownCommentId
		);
		if ($lastKnownComment instanceof IComment) {
			$query->andWhere(
				$query->expr()->lte(
					'creation_timestamp',
					$query->createNamedParameter($lastKnownComment->getCreationDateTime()->getTimestamp()
				)),
				$query->expr()->lte(
					'id',
					$query->createNamedParameter($lastKnownComment->getId()
				))

			);
		}

		$resultStatement = $query->execute();
		while ($data = $resultStatement->fetch()) {
			$comment = new Comment($this->normalizeDatabaseData($data));
			$this->cache($comment);
			$comments[] = $comment;
		}
		$resultStatement->closeCursor();

		return $comments;
	}

	/**
	 * @param string $objectType
	 * @param string $objectId
	 * @param int $id
	 * @return Comment|null
	 */
	protected function getLastKnownCommentTalkVersion($objectType,
													$objectId,
													$id) {
		$query = $this->dbConn->getQueryBuilder();
		$query->select('*')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($objectId)))
			->andWhere($query->expr()->eq('id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row) {
			$comment = new Comment($this->normalizeDatabaseData($row));
			$this->cache($comment);
			return $comment;
		}

		return null;
	}
}

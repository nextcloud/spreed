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
	 * @param array $data
	 * @return IComment
	 */
	public function getCommentFromData(array $data): IComment {
		return new Comment($this->normalizeDatabaseData($data));
	}

	/**
	 * @param string $objectType
	 * @param string $objectId
	 * @param string $verb
	 * @param string $actorType
	 * @param string[] $actors
	 * @return array
	 */
	public function getLastCommentDateByActor(
		$objectType,
		$objectId,
		$verb,
		$actorType,
		array $actors
	) {
		$lastComments = [];

		$query = $this->dbConn->getQueryBuilder();
		$query->select('actor_id')
			->selectAlias($query->createFunction('MAX(' . $query->getColumnName('creation_timestamp') . ')'), 'last_comment')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($objectId)))
			->andWhere($query->expr()->eq('verb', $query->createNamedParameter($verb)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->in('actor_id', $query->createNamedParameter($actors, IQueryBuilder::PARAM_STR_ARRAY)))
			->groupBy('actor_id');

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$lastComments[$row['actor_id']] = new \DateTime($row['last_comment']);
		}
		$result->closeCursor();

		return $lastComments;
	}

	public function getNumberOfCommentsForObjectSinceComment($objectType, $objectId, $lastRead, $verb = ''): int {
		$query = $this->dbConn->getQueryBuilder();
		$query->selectAlias($query->createFunction('COUNT(' . $query->getColumnName('id') . ')'), 'num_messages')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($objectId)))
			->andWhere($query->expr()->gt('id', $query->createNamedParameter($lastRead)));

		if ($verb !== '') {
			$query->andWhere($query->expr()->eq('verb', $query->createNamedParameter($verb)));
		}

		$result = $query->execute();
		$data = $result->fetch();
		$result->closeCursor();

		return isset($data['num_messages']) ? (int) $data['num_messages'] : 0;
	}
}

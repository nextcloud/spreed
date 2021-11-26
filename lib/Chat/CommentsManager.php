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
	 * TODO Remove when server version with https://github.com/nextcloud/server/pull/29921 is required
	 *
	 * @param string $objectType
	 * @param string $objectId
	 * @param int $lastRead
	 * @param string[] $verbs
	 * @return int
	 */
	public function getNumberOfCommentsWithVerbsForObjectSinceComment(string $objectType, string $objectId, int $lastRead, array $verbs): int {
		$query = $this->dbConn->getQueryBuilder();
		$query->select($query->func()->count('id', 'num_messages'))
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($objectId)))
			->andWhere($query->expr()->gt('id', $query->createNamedParameter($lastRead)))
			->andWhere($query->expr()->in('verb', $query->createNamedParameter($verbs, IQueryBuilder::PARAM_STR_ARRAY)));

		$result = $query->executeQuery();
		$data = $result->fetch();
		$result->closeCursor();

		return (int) ($data['num_messages'] ?? 0);
	}
}

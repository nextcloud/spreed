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

use OCP\DB\Exception;
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
			$comments[(int) $row['id']] = $this->getCommentFromData($row);
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
			$reactions[(int) $row['parent_id']] ??= [];
			$reactions[(int) $row['parent_id']][] = $row['reaction'];
		}
		$result->closeCursor();

		return $reactions;
	}

	/**
	 * @param integer $roomId
	 * @param \DateTime $min
	 * @param \DateTime $max
	 * @return int[]
	 */
	public function getMessageIdsByRoomIdInDateInterval(int $roomId, \DateTime $min, \DateTime $max): array {
		$query = $this->dbConn->getQueryBuilder();
		$query->select('id')
			->from('comments')
			->where(
				$query->expr()->andX(
					$query->expr()->eq('object_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)),
					$query->expr()->eq('object_type', $query->createNamedParameter('chat')),
					$query->expr()->gte('creation_timestamp', $query->createNamedParameter($min, IQueryBuilder::PARAM_DATE)),
					$query->expr()->lte('creation_timestamp', $query->createNamedParameter($max, IQueryBuilder::PARAM_DATE))
				)
			);
		$result = $query->executeQuery();
		$ids = [];
		while ($row = $result->fetch()) {
			$ids[] = (int) $row['id'];
		}
		return $ids;
	}
}

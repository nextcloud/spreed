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

namespace OCA\Spreed\Chat;


use OC\Comments\Comment;
use OC\Comments\Manager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\ILogger;

class CommentsManager extends Manager {

	/** @var ITimeFactory */
	protected $timeFactory;

	public function __construct(
		IDBConnection $db,
		ILogger $logger,
		IConfig $config,
		ITimeFactory $timeFactory
	) {
		parent::__construct($db, $logger, $config);
		$this->timeFactory = $timeFactory;
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
	 * @param string $objectType the object type, e.g. 'files'
	 * @param string $objectId the id of the object
	 * @param int $lastKnownCommentId the last known comment (will be used as offset)
	 * @param string $sortDirection direction of the comments (`asc` or `desc`)
	 * @param int $limit optional, number of maximum comments to be returned. if
	 * set to 0, all comments are returned.
	 * @param bool $includeLastKnown
	 * @return IComment[]
	 * @return array
	 */
	public function getForObjectSince(
		string $objectType,
		string $objectId,
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
				$idComparison = $includeLastKnown ? 'lte' : 'lt';
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
							$query->expr()->$idComparison('id', $query->createNamedParameter($lastKnownCommentId))
						)
					)
				);
			} else {
				$idComparison = $includeLastKnown ? 'gte' : 'gt';
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
							$query->expr()->$idComparison('id', $query->createNamedParameter($lastKnownCommentId))
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

	/**
	 * @param string $objectType
	 * @param string $objectId
	 * @param string $verb
	 * @param string $actorType
	 * @param string[] $actors
	 * @return array
	 */
	public function getLastCommentDateByActor(
		string $objectType,
		string $objectId,
		string $verb,
		string $actorType,
		array $actors
	): array {
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
			$lastComments[$row['actor_id']] = $this->timeFactory->getDateTime($row['last_comment']);
		}
		$result->closeCursor();

		return $lastComments;
	}

	public function getNumberOfCommentsForObjectSinceComment(string $objectType, string $objectId, int $lastRead, string $verb = ''): int {
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

		return (int) ($data['num_messages'] ?? 0);
	}

	public function getLastCommentBeforeDate(string $objectType, string $objectId, \DateTime $beforeDate, string $verb = ''): int {
		$query = $this->dbConn->getQueryBuilder();
		$query->select('id')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($objectId)))
			->andWhere($query->expr()->lt('creation_timestamp', $query->createNamedParameter($beforeDate, IQueryBuilder::PARAM_DATE)))
			->orderBy('creation_timestamp', 'desc');

		if ($verb !== '') {
			$query->andWhere($query->expr()->eq('verb', $query->createNamedParameter($verb)));
		}

		$result = $query->execute();
		$data = $result->fetch();
		$result->closeCursor();

		return (int) ($data['id'] ?? 0);
	}
}

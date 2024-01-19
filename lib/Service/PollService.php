<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Service;

use OCA\Talk\Exceptions\WrongPermissionsException;
use OCA\Talk\Model\Poll;
use OCA\Talk\Model\PollMapper;
use OCA\Talk\Model\Vote;
use OCA\Talk\Model\VoteMapper;
use OCA\Talk\Participant;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Comments\ICommentsManager;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PollService {

	public function __construct(
		protected IDBConnection $connection,
		protected PollMapper $pollMapper,
		protected VoteMapper $voteMapper,
	) {
	}

	public function createPoll(int $roomId, string $actorType, string $actorId, string $displayName, string $question, array $options, int $resultMode, int $maxVotes): Poll {
		$question = trim($question);

		if ($question === '' || strlen($question) > 32_000) {
			throw new \UnexpectedValueException();
		}

		try {
			json_encode($options, JSON_THROW_ON_ERROR, 1);
		} catch (\Exception $e) {
			throw new \RuntimeException();
		}

		$validOptions = [];
		foreach ($options as $option) {
			if (!is_string($option)) {
				throw new \RuntimeException();
			}

			$option = trim($option);
			if ($option !== '') {
				$validOptions[] = $option;
			}
		}

		if (count($validOptions) < 2) {
			throw new \RuntimeException();
		}

		try {
			$jsonOptions = json_encode($validOptions, JSON_THROW_ON_ERROR, 1);
		} catch (\Exception $e) {
			throw new \RuntimeException();
		}

		if (strlen($jsonOptions) > 60_000) {
			throw new \UnexpectedValueException();
		}

		$poll = new Poll();
		$poll->setRoomId($roomId);
		$poll->setActorType($actorType);
		$poll->setActorId($actorId);
		$poll->setDisplayName($displayName);
		$poll->setQuestion($question);
		$poll->setOptions($jsonOptions);
		$poll->setVotes(json_encode([]));
		$poll->setResultMode($resultMode);
		$poll->setMaxVotes($maxVotes);

		$this->pollMapper->insert($poll);

		return $poll;
	}

	/**
	 * @param int $roomId
	 * @param int $pollId
	 * @return Poll
	 * @throws DoesNotExistException
	 */
	public function getPoll(int $roomId, int $pollId): Poll {
		$poll = $this->pollMapper->getByPollId($pollId);

		if ($poll->getRoomId() !== $roomId) {
			throw new DoesNotExistException('Room id mismatch');
		}

		return $poll;
	}

	/**
	 * @param Participant $participant
	 * @param Poll $poll
	 * @throws WrongPermissionsException
	 * @throws Exception
	 */
	public function updatePoll(Participant $participant, Poll $poll): void {
		if (!$participant->hasModeratorPermissions()
		 && ($poll->getActorType() !== $participant->getAttendee()->getActorType()
		 || $poll->getActorId() !== $participant->getAttendee()->getActorId())) {
			// Only moderators and the author of the poll can update it
			throw new WrongPermissionsException();
		}

		$this->pollMapper->update($poll);
	}

	/**
	 * @param Participant $participant
	 * @param Poll $poll
	 * @return Vote[]
	 */
	public function getVotesForActor(Participant $participant, Poll $poll): array {
		return $this->voteMapper->findByPollIdForActor(
			$poll->getId(),
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId()
		);
	}

	/**
	 * @param Poll $poll
	 * @return Vote[]
	 */
	public function getVotes(Poll $poll): array {
		return $this->voteMapper->findByPollId($poll->getId());
	}

	/**
	 * @param Participant $participant
	 * @param Poll $poll
	 * @param int[] $optionIds Options the user voted for
	 * @return Vote[]
	 * @throws \RuntimeException
	 */
	public function votePoll(Participant $participant, Poll $poll, array $optionIds): array {
		$numVotes = count($optionIds);
		if ($numVotes !== count(array_unique($optionIds))) {
			throw new \UnexpectedValueException();
		}

		if ($poll->getMaxVotes() !== Poll::MAX_VOTES_UNLIMITED
			&& $poll->getMaxVotes() < $numVotes) {
			throw new \OverflowException();
		}

		if (!empty($optionIds)) {
			foreach ($optionIds as $optionId) {
				if (!is_numeric($optionId)) {
					throw new \RangeException();
				}
			}

			$maxOptionId = max(array_keys(json_decode($poll->getOptions(), true, 512, JSON_THROW_ON_ERROR)));
			$maxVotedId = max($optionIds);
			$minVotedId = min($optionIds);
			if ($minVotedId < 0 || $maxVotedId > $maxOptionId) {
				throw new \RangeException();
			}
		}

		$votes = [];
		$result = json_decode($poll->getVotes(), true);

		$previousVotes = $this->voteMapper->findByPollIdForActor(
			$poll->getId(),
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId()
		);

		$numVoters = $poll->getNumVoters();
		if ($previousVotes && $numVoters > 0) {
			$numVoters--;
		}

		foreach ($previousVotes as $vote) {
			$result[$vote->getOptionId()] ??= 1;
			$result[$vote->getOptionId()] -= 1;
		}

		$this->connection->beginTransaction();
		try {
			$this->voteMapper->deleteVotesByActor(
				$poll->getId(),
				$participant->getAttendee()->getActorType(),
				$participant->getAttendee()->getActorId()
			);

			if (!empty($optionIds)) {
				$numVoters++;
			}

			foreach ($optionIds as $optionId) {
				$vote = new Vote();
				$vote->setPollId($poll->getId());
				$vote->setRoomId($poll->getRoomId());
				$vote->setActorType($participant->getAttendee()->getActorType());
				$vote->setActorId($participant->getAttendee()->getActorId());
				$vote->setDisplayName($participant->getAttendee()->getDisplayName());
				$vote->setOptionId($optionId);
				$this->voteMapper->insert($vote);

				$result[$optionId] ??= 0;
				$result[$optionId] += 1;
				$votes[] = $vote;
			}
		} catch (\Exception $e) {
			$this->connection->rollBack();
			throw $e;
		}
		$this->connection->commit();

		$this->updateResultCache($poll->getId());
		$result = array_filter($result);
		$poll->setVotes(json_encode($result));
		$poll->setNumVoters($numVoters);

		return $votes;
	}

	public function updateResultCache(int $pollId): void {
		$resultQuery = $this->connection->getQueryBuilder();
		$resultQuery->selectAlias(
			$resultQuery->func()->concat(
				$resultQuery->expr()->literal('"'),
				'option_id',
				$resultQuery->expr()->literal('":'),
				$resultQuery->func()->count('id')
			),
			'colonseparatedvalue'
		)
			->from('talk_poll_votes')
			->where($resultQuery->expr()->eq('poll_id', $resultQuery->createNamedParameter($pollId)))
			->groupBy('option_id')
			->orderBy('option_id', 'ASC');

		$jsonQuery = $this->connection->getQueryBuilder();
		$jsonQuery
			->selectAlias(
				$jsonQuery->func()->concat(
					$jsonQuery->expr()->literal('{'),
					$jsonQuery->func()->groupConcat('colonseparatedvalue'),
					$jsonQuery->expr()->literal('}')
				),
				'json'
			)
			->from($jsonQuery->createFunction('(' . $resultQuery->getSQL() . ')'), 'json');

		$subQuery = $this->connection->getQueryBuilder();
		$subQuery->select('actor_type', 'actor_id')
			->from('talk_poll_votes')
			->where($subQuery->expr()->eq('poll_id', $subQuery->createNamedParameter($pollId)))
			->groupBy('actor_type', 'actor_id');

		$votersQuery = $this->connection->getQueryBuilder();
		$votersQuery->select($votersQuery->func()->count('*'))
			->from($votersQuery->createFunction('(' . $subQuery->getSQL() . ')'), 'voters');

		$update = $this->connection->getQueryBuilder();
		$update->update('talk_polls')
			->set('votes', $jsonQuery->createFunction('(' . $jsonQuery->getSQL() . ')'))
			->set('num_voters', $jsonQuery->createFunction('(' . $votersQuery->getSQL() . ')'))
			->where($update->expr()->eq('id', $update->createNamedParameter($pollId, IQueryBuilder::PARAM_INT)));

		$this->connection->beginTransaction();
		try {
			$update->executeStatement();

			// Fix `null` being stored if the only voter revokes their vote
			$updateFixNull = $this->connection->getQueryBuilder();
			$updateFixNull->update('talk_polls')
				->set('votes', $updateFixNull->createNamedParameter('{}'))
				->where($updateFixNull->expr()->eq('id', $updateFixNull->createNamedParameter($pollId, IQueryBuilder::PARAM_INT)))
				->andWhere($updateFixNull->expr()->isNull('votes'));

			$updateFixNull->executeStatement();
		} catch (\Exception $e) {
			$this->connection->rollBack();
			throw $e;
		}
		$this->connection->commit();
	}

	public function deleteByRoomId(int $roomId): void {
		$this->voteMapper->deleteByRoomId($roomId);
		$this->pollMapper->deleteByRoomId($roomId);
	}

	public function deleteByPollId(int $pollId): void {
		$this->voteMapper->deleteByPollId($pollId);
		$this->pollMapper->deleteByPollId($pollId);
	}

	public function updateDisplayNameForActor(string $actorType, string $actorId, string $displayName): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_polls')
			->set('display_name', $update->createNamedParameter($displayName))
			->where($update->expr()->eq('actor_type', $update->createNamedParameter($actorType)))
			->andWhere($update->expr()->eq('actor_id', $update->createNamedParameter($actorId)));
		$update->executeStatement();

		$update = $this->connection->getQueryBuilder();
		$update->update('talk_poll_votes')
			->set('display_name', $update->createNamedParameter($displayName))
			->where($update->expr()->eq('actor_type', $update->createNamedParameter($actorType)))
			->andWhere($update->expr()->eq('actor_id', $update->createNamedParameter($actorId)));
		$update->executeStatement();
	}

	public function neutralizeDeletedUser(string $actorType, string $actorId): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_polls')
			->set('display_name', $update->createNamedParameter(''))
			->set('actor_type', $update->createNamedParameter(ICommentsManager::DELETED_USER))
			->set('actor_id', $update->createNamedParameter(ICommentsManager::DELETED_USER))
			->where($update->expr()->eq('actor_type', $update->createNamedParameter($actorType)))
			->andWhere($update->expr()->eq('actor_id', $update->createNamedParameter($actorId)));
		$update->executeStatement();

		$update = $this->connection->getQueryBuilder();
		$update->update('talk_poll_votes')
			->set('display_name', $update->createNamedParameter(''))
			->set('actor_type', $update->createNamedParameter(ICommentsManager::DELETED_USER))
			->set('actor_id', $update->createNamedParameter(ICommentsManager::DELETED_USER))
			->where($update->expr()->eq('actor_type', $update->createNamedParameter($actorType)))
			->andWhere($update->expr()->eq('actor_id', $update->createNamedParameter($actorId)));
		$update->executeStatement();
	}
}

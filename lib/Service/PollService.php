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
use OCP\DB\Exception;
use OCP\IDBConnection;

class PollService {
	protected IDBConnection $connection;
	protected PollMapper $pollMapper;
	protected VoteMapper $voteMapper;

	public function __construct(IDBConnection $connection,
								PollMapper $pollMapper,
								VoteMapper $voteMapper) {
		$this->connection = $connection;
		$this->pollMapper = $pollMapper;
		$this->voteMapper = $voteMapper;
	}

	public function createPoll(int $roomId, string $actorType, string $actorId, string $displayName, string $question, array $options, int $resultMode, int $maxVotes): Poll {
		$poll = new Poll();
		$poll->setRoomId($roomId);
		$poll->setActorType($actorType);
		$poll->setActorId($actorId);
		$poll->setDisplayName($displayName);
		$poll->setQuestion($question);
		$poll->setOptions(json_encode($options));
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
			// Only moderators and the author of the poll can close it
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
	 * @param Participant $participant
	 * @param Poll $poll
	 * @param int[] $optionIds Options the user voted for
	 * @return Vote[]
	 */
	public function votePoll(Participant $participant, Poll $poll, array $optionIds): array {
		$votes = [];

		$this->connection->beginTransaction();
		try {
			$this->voteMapper->deleteVotesByActor(
				$poll->getId(),
				$participant->getAttendee()->getActorType(),
				$participant->getAttendee()->getActorId()
			);

			foreach ($optionIds as $optionId) {
				$vote = new Vote();
				$vote->setPollId($poll->getId());
				$vote->setRoomId($poll->getRoomId());
				$vote->setActorType($participant->getAttendee()->getActorType());
				$vote->setActorId($participant->getAttendee()->getActorId());
				$vote->setDisplayName($participant->getAttendee()->getDisplayName());
				$vote->setOptionId($optionId);
				$this->voteMapper->insert($vote);

				$votes[] = $vote;
			}
		} catch (\Exception $e) {
			$this->connection->rollBack();
			throw $e;
		}
		$this->connection->commit();

		return $votes;
	}

	public function deleteByRoomId(int $roomId): void {
		$this->voteMapper->deleteByRoomId($roomId);
		$this->pollMapper->deleteByRoomId($roomId);
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
}

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
use OCA\Talk\Model\VoteMapper;
use OCA\Talk\Participant;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PollService {
	protected PollMapper $pollMapper;
	protected VoteMapper $voteMapper;

	public function __construct(PollMapper $pollMapper,
								VoteMapper $voteMapper) {
		$this->pollMapper = $pollMapper;
		$this->voteMapper = $voteMapper;
	}

	public function createPoll(int $roomId, string $actorType, string $actorId, string $question, array $options, int $resultMode, int $maxVotes): Poll {
		$poll = new Poll();
		$poll->setRoomId($roomId);
		$poll->setActorType($actorType);
		$poll->setActorId($actorId);
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
	 * @throws IMapperException
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
}

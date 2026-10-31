<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Model;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Poll;
use OCA\Talk\Model\PollMapper;
use OCA\Talk\Model\Vote;
use OCA\Talk\Model\VoteMapper;
use OCA\Talk\Service\PollService;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class PollMapperTest extends TestCase {
	protected PollMapper $pollMapper;
	protected VoteMapper $voteMapper;
	protected PollService $service;
	protected int $roomId;

	public function setUp(): void {
		parent::setUp();

		$connection = Server::get(IDBConnection::class);
		$this->pollMapper = new PollMapper($connection);
		$this->voteMapper = new VoteMapper($connection);
		$this->service = new PollService(
			$connection,
			$this->pollMapper,
			$this->voteMapper,
		);
		$this->roomId = random_int(1_000_000, 9_000_000);
	}

	public function tearDown(): void {
		$this->service->deleteByRoomId($this->roomId);
		parent::tearDown();
	}

	protected function createPollWithVote(?\DateTime $expireDate): Poll {
		$poll = new Poll();
		$poll->setRoomId($this->roomId);
		$poll->setQuestion('Question');
		$poll->setOptions(['A', 'B']);
		$poll->setVotes(json_encode([]));
		$poll->setActorType(Attendee::ACTOR_USERS);
		$poll->setActorId('alice');
		$poll->setDisplayName('Alice');
		$poll->setExpireDate($expireDate);
		$this->pollMapper->insert($poll);

		$vote = new Vote();
		$vote->setPollId($poll->getId());
		$vote->setRoomId($this->roomId);
		$vote->setActorType(Attendee::ACTOR_USERS);
		$vote->setActorId('bob');
		$vote->setDisplayName('Bob');
		$vote->setOptionId(0);
		$this->voteMapper->insert($vote);

		return $poll;
	}

	public function testDeleteExpired(): void {
		$expired = $this->createPollWithVote(new \DateTime('-1 hour'));
		$future = $this->createPollWithVote(new \DateTime('+1 hour'));
		$never = $this->createPollWithVote(null);

		$this->service->deleteExpired(new \DateTime());

		$this->assertSame([], $this->voteMapper->findByPollId($expired->getId()));
		$this->assertCount(1, $this->voteMapper->findByPollId($future->getId()));
		$this->assertCount(1, $this->voteMapper->findByPollId($never->getId()));

		$this->assertSame(
			[$future->getId(), $never->getId()],
			array_map(static fn (Poll $poll): int => $poll->getId(), array_values(array_filter([
				$this->findPoll($expired->getId()),
				$this->findPoll($future->getId()),
				$this->findPoll($never->getId()),
			]))),
		);
	}

	protected function findPoll(int $pollId): ?Poll {
		try {
			return $this->pollMapper->getPollByRoomIdAndPollId($this->roomId, $pollId);
		} catch (\Exception) {
			return null;
		}
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Migration;

use OCA\Talk\Config;
use OCA\Talk\Migration\CleanupExpiredMessageLeftovers;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\AttachmentMapper;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Poll;
use OCA\Talk\Model\PollMapper;
use OCA\Talk\Model\Vote;
use OCA\Talk\Model\VoteMapper;
use OCA\Talk\Service\PollService;
use OCA\Talk\SetupCheck\ExpensiveRepairSteps;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Comments\ICommentsManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class CleanupExpiredMessageLeftoversTest extends TestCase {
	protected IDBConnection $connection;
	protected ICommentsManager $commentsManager;
	protected AttachmentMapper $attachmentMapper;
	protected PollMapper $pollMapper;
	protected VoteMapper $voteMapper;
	protected int $roomId;
	protected int $otherRoomId;

	#[\Override]
	public function setUp(): void {
		parent::setUp();

		$this->connection = Server::get(IDBConnection::class);
		$this->commentsManager = Server::get(ICommentsManager::class);
		$this->attachmentMapper = new AttachmentMapper($this->connection);
		$this->pollMapper = new PollMapper($this->connection);
		$this->voteMapper = new VoteMapper($this->connection);
		$this->roomId = random_int(1_000_000, 4_000_000);
		$this->otherRoomId = random_int(5_000_000, 9_000_000);
	}

	#[\Override]
	public function tearDown(): void {
		$pollService = new PollService($this->connection, $this->pollMapper, $this->voteMapper);
		foreach ([$this->roomId, $this->otherRoomId] as $roomId) {
			$this->attachmentMapper->deleteByRoomId($roomId);
			$pollService->deleteByRoomId($roomId);
			$this->commentsManager->deleteCommentsAtObject('chat', (string)$roomId);
		}
		parent::tearDown();
	}

	protected function createMessage(string $message, ?\DateTime $expireDate): int {
		$comment = $this->commentsManager->create(Attendee::ACTOR_USERS, 'alice', 'chat', (string)$this->roomId);
		$comment->setMessage($message);
		$comment->setVerb('object_shared');
		$comment->setExpireDate($expireDate);
		$this->commentsManager->save($comment);
		return (int)$comment->getId();
	}

	protected function createExpiredMessageId(): int {
		$messageId = $this->createMessage('expired', null);
		$this->commentsManager->delete((string)$messageId);
		return $messageId;
	}

	protected function createAttachment(int $roomId, int $messageId, string $objectType): Attachment {
		$attachment = new Attachment();
		$attachment->setRoomId($roomId);
		$attachment->setMessageId($messageId);
		$attachment->setMessageTime(time());
		$attachment->setObjectType($objectType);
		$attachment->setActorType(Attendee::ACTOR_USERS);
		$attachment->setActorId('alice');
		return $this->attachmentMapper->insert($attachment);
	}

	protected function createPoll(int $roomId, int $status = Poll::STATUS_OPEN): Poll {
		$poll = new Poll();
		$poll->setRoomId($roomId);
		$poll->setQuestion('Question');
		$poll->setOptions(['A', 'B']);
		$poll->setVotes(json_encode([]));
		$poll->setActorType(Attendee::ACTOR_USERS);
		$poll->setActorId('alice');
		$poll->setDisplayName('Alice');
		$poll->setStatus($status);
		$this->pollMapper->insert($poll);

		$vote = new Vote();
		$vote->setPollId($poll->getId());
		$vote->setRoomId($roomId);
		$vote->setActorType(Attendee::ACTOR_USERS);
		$vote->setActorId('bob');
		$vote->setDisplayName('Bob');
		$vote->setOptionId(0);
		$this->voteMapper->insert($vote);

		return $poll;
	}

	protected function pollMessage(Poll $poll): string {
		return json_encode([
			'message' => 'object_shared',
			'parameters' => [
				'objectType' => 'talk-poll',
				'objectId' => $poll->getId(),
			],
		]);
	}

	/**
	 * @return array{expire_date: ?string}|null
	 */
	protected function findAttachment(int $id): ?array {
		$query = $this->connection->getQueryBuilder();
		$query->select('expire_date')
			->from('talk_attachments')
			->where($query->expr()->eq('id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$result = $query->executeQuery();
		$row = $result->fetchAssociative();
		$result->closeCursor();
		return $row ?: null;
	}

	protected function findPoll(Poll $poll): ?Poll {
		try {
			return $this->pollMapper->getPollByRoomIdAndPollId($poll->getRoomId(), $poll->getId());
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function testRun(): void {
		$expireDate = new \DateTime('+1 hour');

		$expiringPoll = $this->createPoll($this->roomId);
		$expiringPollAttachment = $this->createAttachment($this->roomId, $this->createMessage($this->pollMessage($expiringPoll), $expireDate), Attachment::TYPE_POLL);
		$permanentPoll = $this->createPoll($this->roomId);
		$permanentPollAttachment = $this->createAttachment($this->roomId, $this->createMessage($this->pollMessage($permanentPoll), null), Attachment::TYPE_POLL);
		$permanentFile = $this->createAttachment($this->roomId, $this->createMessage('file', null), Attachment::TYPE_FILE);
		$expiredPoll = $this->createPoll($this->roomId);
		$expiredPollAttachment = $this->createAttachment($this->roomId, $this->createExpiredMessageId(), Attachment::TYPE_POLL);
		$expiredFile = $this->createAttachment($this->roomId, $this->createExpiredMessageId(), Attachment::TYPE_FILE);
		$draftPoll = $this->createPoll($this->roomId, Poll::STATUS_DRAFT);
		$otherRoomPoll = $this->createPoll($this->otherRoomId);

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects($this->once())
			->method('setAppValueInt')
			->with(Config::EXPENSIVE_REPAIR_PERFORMED, ExpensiveRepairSteps::VERSION);

		$repairStep = new CleanupExpiredMessageLeftovers($this->connection, $appConfig);
		$repairStep->run($this->createMock(IOutput::class));

		$this->assertSame($expireDate->format('Y-m-d H:i:s'), $this->findAttachment($expiringPollAttachment->getId())['expire_date'] ?? null);
		$this->assertSame($expireDate->getTimestamp(), $this->findPoll($expiringPoll)?->getExpireDate()?->getTimestamp());
		$this->assertCount(1, $this->voteMapper->findByPollId($expiringPoll->getId()));

		$this->assertSame(['expire_date' => null], $this->findAttachment($permanentPollAttachment->getId()));
		$this->assertNull($this->findPoll($permanentPoll)?->getExpireDate());
		$this->assertSame(['expire_date' => null], $this->findAttachment($permanentFile->getId()));

		$this->assertNull($this->findAttachment($expiredPollAttachment->getId()));
		$this->assertNull($this->findAttachment($expiredFile->getId()));
		$this->assertNull($this->findPoll($expiredPoll));
		$this->assertSame([], $this->voteMapper->findByPollId($expiredPoll->getId()));

		$this->assertNotNull($this->findPoll($draftPoll));
		$this->assertNotNull($this->findPoll($otherRoomPoll));
	}
}

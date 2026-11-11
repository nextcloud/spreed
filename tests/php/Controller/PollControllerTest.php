<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Controller\PollController;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Poll;
use OCA\Talk\Model\Vote;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\PollExportService;
use OCA\Talk\Service\PollService;
use OCA\Talk\Service\ThreadService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class PollControllerTest extends TestCase {

	private PollController $controller;
	private PollService&MockObject $service;

	public function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(PollService::class);
		$this->controller = new PollController(
			'spreed',
			$this->createMock(IRequest::class),
			$this->createMock(ChatManager::class),
			$this->service,
			$this->createMock(PollExportService::class),
			$this->createMock(ThreadService::class),
			$this->createMock(ITimeFactory::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class),
		);
		$attendee = new Attendee();
		$attendee->setActorType('users');
		$attendee->setActorId('alice');
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);
		$this->controller->setParticipant($participant);
		$this->controller->setRoom($this->createMock(Room::class));
	}

	private function createPoll(int $status, int $mode, bool $liveVoters): Poll {
		$poll = new Poll();
		$poll->setId(1);
		$poll->setQuestion('Lunch?');
		$poll->setOptions(['Pizza', 'Pasta']);
		$poll->setActorType('users');
		$poll->setActorId('bob');
		$poll->setDisplayName('Bob');
		$poll->setStatus($status);
		$poll->setResultMode($mode);
		$poll->setShowVotersBeforeClosing($liveVoters);
		$poll->setVotes('{"0":1}');
		$poll->setNumVoters(1);
		return $poll;
	}

	private function createVote(): Vote {
		$vote = new Vote();
		$vote->setActorType('users');
		$vote->setActorId('alice');
		$vote->setDisplayName('Alice');
		$vote->setOptionId(0);
		return $vote;
	}

	public static function visibilityCases(): array {
		return [
			'open enabled voter' => [Poll::STATUS_OPEN, Poll::MODE_PUBLIC, true, true, true],
			'open disabled voter' => [Poll::STATUS_OPEN, Poll::MODE_PUBLIC, false, true, false],
			'open enabled non-voter' => [Poll::STATUS_OPEN, Poll::MODE_PUBLIC, true, false, false],
			'open disabled non-voter' => [Poll::STATUS_OPEN, Poll::MODE_PUBLIC, false, false, false],
			'open hidden voter' => [Poll::STATUS_OPEN, Poll::MODE_HIDDEN, true, true, false],
			'open hidden non-voter' => [Poll::STATUS_OPEN, Poll::MODE_HIDDEN, true, false, false],
			'closed public voter' => [Poll::STATUS_CLOSED, Poll::MODE_PUBLIC, false, true, true],
			'closed public non-voter' => [Poll::STATUS_CLOSED, Poll::MODE_PUBLIC, false, false, true],
			'closed enabled non-voter' => [Poll::STATUS_CLOSED, Poll::MODE_PUBLIC, true, false, true],
			'closed hidden voter' => [Poll::STATUS_CLOSED, Poll::MODE_HIDDEN, true, true, false],
			'draft enabled' => [Poll::STATUS_DRAFT, Poll::MODE_PUBLIC, true, false, false],
		];
	}

	#[DataProvider('visibilityCases')]
	public function testShowPollVoterVisibility(int $status, int $mode, bool $liveVoters, bool $hasVoted, bool $expectDetails): void {
		$poll = $this->createPoll($status, $mode, $liveVoters);
		$vote = $this->createVote();
		$this->service->method('getPoll')->willReturn($poll);
		$this->service->method('getVotesForActor')->willReturn($hasVoted ? [$vote] : []);
		$this->service->expects($expectDetails ? $this->once() : $this->never())->method('getVotes')->with($poll)->willReturn([$vote]);

		$data = $this->controller->showPoll(1)->getData();
		$this->assertSame($expectDetails, array_key_exists('details', $data));
		if ($expectDetails) {
			$this->assertSame([$vote->asArray()], $data['details']);
		}
		if ($status === Poll::STATUS_OPEN && (!$hasVoted || $mode === Poll::MODE_HIDDEN)) {
			$this->assertSame([], $data['votes']);
		}
	}

	public function testVoteResponseIncludesLiveVoters(): void {
		$poll = $this->createPoll(Poll::STATUS_OPEN, Poll::MODE_PUBLIC, true);
		$vote = $this->createVote();
		$this->service->method('getPoll')->willReturn($poll);
		$this->service->method('votePoll')->willReturn([$vote]);
		$this->service->expects($this->once())->method('getVotes')->with($poll)->willReturn([$vote]);

		$data = $this->controller->votePoll(1, [0])->getData();
		$this->assertSame([$vote->asArray()], $data['details']);
	}

	public function testWithdrawingVoteHidesLiveVoters(): void {
		$poll = $this->createPoll(Poll::STATUS_OPEN, Poll::MODE_PUBLIC, true);
		$this->service->method('getPoll')->willReturn($poll);
		$this->service->method('votePoll')->willReturn([]);
		$this->service->expects($this->never())->method('getVotes');

		$data = $this->controller->votePoll(1, [])->getData();
		$this->assertArrayNotHasKey('details', $data);
		$this->assertSame([], $data['votes']);
	}

	public static function resultVisibilityCases(): array {
		$cases = [];
		foreach ([Poll::MODE_PUBLIC, Poll::MODE_HIDDEN] as $mode) {
			foreach ([false, true] as $preview) {
				foreach ([false, true] as $hasVoted) {
					foreach ([false, true] as $liveVoters) {
						$cases[] = [$mode, $preview, $hasVoted, $liveVoters];
					}
				}
			}
		}
		return $cases;
	}

	#[DataProvider('resultVisibilityCases')]
	public function testResultAndNameVisibilityOptions(int $mode, bool $preview, bool $hasVoted, bool $liveVoters): void {
		$poll = $this->createPoll(Poll::STATUS_OPEN, $mode, $liveVoters);
		$poll->setShowResultsBeforeVoting($preview);
		$vote = $this->createVote();
		$this->service->method('getPoll')->willReturn($poll);
		$this->service->method('getVotesForActor')->willReturn($hasVoted ? [$vote] : []);
		$expectDetails = $mode === Poll::MODE_PUBLIC && $liveVoters && ($hasVoted || $preview);
		$this->service->expects($expectDetails ? $this->once() : $this->never())->method('getVotes')->willReturn([$vote]);

		$data = $this->controller->showPoll(1)->getData();
		$expectResults = $preview || ($mode === Poll::MODE_PUBLIC && $hasVoted);
		$this->assertSame($expectResults ? ['option-0' => 1] : [], $data['votes']);
		$this->assertSame($expectResults ? 1 : 0, $data['numVoters']);
		$this->assertSame($expectDetails, array_key_exists('details', $data));
	}

	public function testWithdrawingVoteKeepsEnabledPreviewAndNames(): void {
		$poll = $this->createPoll(Poll::STATUS_OPEN, Poll::MODE_PUBLIC, true);
		$poll->setShowResultsBeforeVoting(true);
		$this->service->method('getPoll')->willReturn($poll);
		$this->service->method('votePoll')->willReturn([]);
		$this->service->expects($this->once())->method('getVotes')->willReturn([]);

		$data = $this->controller->votePoll(1, [])->getData();
		$this->assertSame([], $data['details']);
		$this->assertSame(['option-0' => 1], $data['votes']);
	}
}

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
	private PollExportService&MockObject $exportService;

	public function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(PollService::class);
		$this->exportService = $this->createMock(PollExportService::class);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-11-23'));
		$this->controller = new PollController(
			'spreed',
			$this->createMock(IRequest::class),
			$this->createMock(ChatManager::class),
			$this->service,
			$this->exportService,
			$this->createMock(ThreadService::class),
			$timeFactory,
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

	private function createPoll(int $status, int $mode): Poll {
		$poll = new Poll();
		$poll->setId(1);
		$poll->setQuestion('Lunch?');
		$poll->setOptions(['Pizza', 'Pasta']);
		$poll->setActorType('users');
		$poll->setActorId('bob');
		$poll->setDisplayName('Bob');
		$poll->setStatus($status);
		$poll->setResultMode($mode);
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
		$cases = [];
		foreach ([Poll::STATUS_OPEN, Poll::STATUS_CLOSED, Poll::STATUS_DRAFT] as $status) {
			foreach (range(0, 15) as $mode) {
				foreach ([false, true] as $hasVoted) {
					$anonymous = ($mode & 1) !== 0;
					$preview = ($mode & 8) !== 0 || (!$anonymous && ($mode & 4) !== 0);
					$results = $status !== Poll::STATUS_OPEN || $preview || (!$anonymous && $hasVoted);
					$names = !$anonymous && $status !== Poll::STATUS_DRAFT
						&& ($status === Poll::STATUS_CLOSED || ($mode & 4) !== 0 || ($hasVoted && ($mode & 2) !== 0));
					$cases["status=$status mode=$mode voted=" . (int)$hasVoted] = [$status, $mode, $hasVoted, $results, $names];
				}
			}
		}
		return $cases;
	}

	#[DataProvider('visibilityCases')]
	public function testShowPollVisibility(int $status, int $mode, bool $hasVoted, bool $expectResults, bool $expectDetails): void {
		if ($status === Poll::STATUS_DRAFT) {
			$participant = $this->createMock(Participant::class);
			$participant->method('hasModeratorPermissions')->willReturn(true);
			$this->controller->setParticipant($participant);
		}
		$poll = $this->createPoll($status, $mode);
		$vote = $this->createVote();
		$this->service->method('getPoll')->willReturn($poll);
		$this->service->method('getVotesForActor')->willReturn($hasVoted ? [$vote] : []);
		$this->service->expects($expectDetails ? $this->once() : $this->never())->method('getVotes')->with($poll)->willReturn([$vote]);

		$data = $this->controller->showPoll(1)->getData();
		$this->assertSame($mode, $data['resultMode']);
		$this->assertSame($expectResults ? ['option-0' => 1] : [], $data['votes']);
		$this->assertSame($expectResults ? 1 : 0, $data['numVoters']);
		$this->assertSame($expectDetails, array_key_exists('details', $data));
		if ($expectDetails) {
			$this->assertSame([$vote->asArray()], $data['details']);
		}
	}

	public function testVoteResponseIncludesLiveVoters(): void {
		$poll = $this->createPoll(Poll::STATUS_OPEN, Poll::MODE_SHOW_NAMES_BEFORE_CLOSING);
		$vote = $this->createVote();
		$this->service->method('getPoll')->willReturn($poll);
		$this->service->method('votePoll')->willReturn([$vote]);
		$this->service->expects($this->once())->method('getVotes')->with($poll)->willReturn([$vote]);

		$data = $this->controller->votePoll(1, [0])->getData();
		$this->assertSame([$vote->asArray()], $data['details']);
	}

	public function testWithdrawingVoteHidesLiveVoters(): void {
		$poll = $this->createPoll(Poll::STATUS_OPEN, Poll::MODE_SHOW_NAMES_BEFORE_CLOSING);
		$this->service->method('getPoll')->willReturn($poll);
		$this->service->method('votePoll')->willReturn([]);
		$this->service->expects($this->never())->method('getVotes');

		$data = $this->controller->votePoll(1, [])->getData();
		$this->assertArrayNotHasKey('details', $data);
		$this->assertSame([], $data['votes']);
	}

	public function testWithdrawingVoteKeepsEnabledPreviewAndNames(): void {
		$poll = $this->createPoll(Poll::STATUS_OPEN, Poll::MODE_SHOW_NAMES_BEFORE_CLOSING);
		$poll->setResultMode(Poll::MODE_SHOW_NAMES_BEFORE_CLOSING | Poll::MODE_SHOW_NAMES_BEFORE_VOTING);
		$this->service->method('getPoll')->willReturn($poll);
		$this->service->method('votePoll')->willReturn([]);
		$this->service->expects($this->once())->method('getVotes')->willReturn([]);

		$data = $this->controller->votePoll(1, [])->getData();
		$this->assertSame([], $data['details']);
		$this->assertSame(['option-0' => 1], $data['votes']);
	}

	public static function exportModes(): array {
		return array_map(static fn (int $mode) => [$mode], range(0, 15));
	}

	#[DataProvider('exportModes')]
	public function testExportRespectsAnonymousFlag(int $mode): void {
		$participant = $this->createMock(Participant::class);
		$participant->method('hasModeratorPermissions')->willReturn(true);
		$this->controller->setParticipant($participant);
		$poll = $this->createPoll(Poll::STATUS_CLOSED, $mode);
		$vote = $this->createVote();
		$anonymous = ($mode & Poll::MODE_HIDDEN) !== 0;
		$this->service->method('getPoll')->willReturn($poll);
		$this->service->expects($anonymous ? $this->never() : $this->once())->method('getVotes')->willReturn([$vote]);
		$this->exportService->expects($this->once())->method('exportToSpreadsheet')->with($poll, $anonymous ? [] : [$vote], 'csv')->willReturn('export');
		$this->assertSame(200, $this->controller->exportPoll(1, 'csv')->getStatus());
	}
}

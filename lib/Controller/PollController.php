<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use JsonException;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Exceptions\PollPropertyException;
use OCA\Talk\Exceptions\WrongPermissionsException;
use OCA\Talk\Middleware\Attribute\FederationSupported;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Middleware\Attribute\RequirePermission;
use OCA\Talk\Middleware\Attribute\RequireReadWriteConversation;
use OCA\Talk\Model\Poll;
use OCA\Talk\Model\Vote;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCA\Talk\Service\AttachmentService;
use OCA\Talk\Service\PollService;
use OCA\Talk\Service\ThreadService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\RequestHeader;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkPoll from ResponseDefinitions
 * @psalm-import-type TalkPollDraft from ResponseDefinitions
 */
class PollController extends AEnvironmentAwareOCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		protected ChatManager $chatManager,
		protected PollService $pollService,
		protected AttachmentService $attachmentService,
		protected ThreadService $threadService,
		protected ITimeFactory $timeFactory,
		protected LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Create a poll
	 *
	 * @param string $question Question of the poll
	 * @param string[] $options Options of the poll
	 * @psalm-param list<string> $options
	 * @param 0|1 $resultMode Mode how the results will be shown
	 * @psalm-param Poll::MODE_* $resultMode Mode how the results will be shown
	 * @param int $maxVotes Number of maximum votes per voter
	 * @param bool $draft Whether the poll should be saved as a draft (only allowed for moderators and with `talk-polls-drafts` capability)
	 * @param int $threadId Thread id which this poll should be posted into (also requires `threads` capability)
	 * @return DataResponse<Http::STATUS_OK, TalkPollDraft, array{}>|DataResponse<Http::STATUS_CREATED, TalkPoll, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'draft'|'options'|'poll'|'question'|'room'}, array{}>
	 *
	 * 200: Draft created successfully
	 * 201: Poll created successfully
	 * 400: Creating poll is not possible
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function createPoll(string $question, array $options, int $resultMode, int $maxVotes, bool $draft = false, int $threadId = 0): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\PollController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\PollController::class);
			return $proxy->createPoll($this->room, $this->participant, $question, $options, $resultMode, $maxVotes, $draft);
		}

		if ($this->room->getType() !== Room::TYPE_GROUP
			&& $this->room->getType() !== Room::TYPE_PUBLIC) {
			return new DataResponse(['error' => PollPropertyException::REASON_ROOM], Http::STATUS_BAD_REQUEST);
		}

		if ($draft === true && !$this->participant->hasModeratorPermissions()) {
			return new DataResponse(['error' => PollPropertyException::REASON_DRAFT], Http::STATUS_BAD_REQUEST);
		}

		$attendee = $this->participant->getAttendee();
		try {
			$poll = $this->pollService->createPoll(
				$this->room->getId(),
				$attendee->getActorType(),
				$attendee->getActorId(),
				$attendee->getDisplayName(),
				$question,
				$options,
				$resultMode,
				$maxVotes,
				$draft,
			);
		} catch (PollPropertyException $e) {
			$this->logger->error('Error creating poll', ['exception' => $e]);
			return new DataResponse(['error' => $e->getReason()], Http::STATUS_BAD_REQUEST);
		}

		if ($draft) {
			return new DataResponse($poll->renderAsDraft());
		}

		if ($threadId !== 0) {
			try {
				$this->threadService->findByThreadId($this->room->getId(), $threadId);
			} catch (DoesNotExistException) {
				// Someone tried to cheat, ignore
				$threadId = 0;
			}
		}

		$message = json_encode([
			'message' => 'object_shared',
			'parameters' => [
				'objectType' => 'talk-poll',
				'objectId' => $poll->getId(),
				'metaData' => [
					'type' => 'talk-poll',
					'id' => $poll->getId(),
					'name' => $question,
				]
			],
		], JSON_THROW_ON_ERROR);

		try {
			$this->chatManager->addSystemMessage($this->room, $this->participant, $attendee->getActorType(), $attendee->getActorId(), $message, $this->timeFactory->getDateTime(), true, threadId: $threadId);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
		}

		return new DataResponse($this->renderPoll($poll), Http::STATUS_CREATED);
	}

	/**
	 * Modify a draft poll
	 *
	 * Required capability: `edit-draft-poll`
	 *
	 * @param int $pollId The poll id
	 * @param string $question Question of the poll
	 * @param string[] $options Options of the poll
	 * @psalm-param list<string> $options
	 * @param 0|1 $resultMode Mode how the results will be shown
	 * @psalm-param Poll::MODE_* $resultMode Mode how the results will be shown
	 * @param int $maxVotes Number of maximum votes per voter
	 * @return DataResponse<Http::STATUS_OK, TalkPollDraft, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array{error: 'draft'|'options'|'poll'|'question'|'room'}, array{}>
	 *
	 * 200: Draft modified successfully
	 * 400: Modifying poll is not possible
	 * 403: No permission to modify this poll
	 * 404: No draft poll exists
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function updateDraftPoll(int $pollId, string $question, array $options, int $resultMode, int $maxVotes): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\PollController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\PollController::class);
			return $proxy->updateDraftPoll($pollId, $this->room, $this->participant, $question, $options, $resultMode, $maxVotes);
		}

		if ($this->room->getType() !== Room::TYPE_GROUP
			&& $this->room->getType() !== Room::TYPE_PUBLIC) {
			return new DataResponse(['error' => PollPropertyException::REASON_ROOM], Http::STATUS_BAD_REQUEST);
		}

		try {
			$poll = $this->pollService->getPoll($this->room->getId(), $pollId);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => PollPropertyException::REASON_POLL], Http::STATUS_NOT_FOUND);
		}

		if (!$poll->isDraft()) {
			return new DataResponse(['error' => PollPropertyException::REASON_POLL], Http::STATUS_BAD_REQUEST);
		}

		if (!$this->participant->hasModeratorPermissions()
			&& ($poll->getActorType() !== $this->participant->getAttendee()->getActorType()
				|| $poll->getActorId() !== $this->participant->getAttendee()->getActorId())) {
			return new DataResponse(['error' => PollPropertyException::REASON_DRAFT], Http::STATUS_BAD_REQUEST);
		}

		try {
			$poll->setQuestion($question);
			$poll->setOptions($options);
			$poll->setResultMode($resultMode);
			$poll->setMaxVotes($maxVotes);
		} catch (PollPropertyException $e) {
			$this->logger->error('Error modifying poll', ['exception' => $e]);
			return new DataResponse(['error' => $e->getReason()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->pollService->updatePoll($this->participant, $poll);
		} catch (WrongPermissionsException $e) {
			$this->logger->error('Error modifying poll', ['exception' => $e]);
			return new DataResponse(['error' => PollPropertyException::REASON_POLL], Http::STATUS_FORBIDDEN);
		}

		return new DataResponse($poll->renderAsDraft());
	}

	/**
	 * Get all drafted polls
	 *
	 * Required capability: `talk-polls-drafts`
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkPollDraft>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Poll returned
	 * 403: User is not a moderator
	 * 404: Poll not found
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function getAllDraftPolls(): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\PollController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\PollController::class);
			return $proxy->getDraftsForRoom($this->room, $this->participant);
		}

		$polls = $this->pollService->getDraftsForRoom($this->room->getId());
		$data = [];
		foreach ($polls as $poll) {
			$data[] = $poll->renderAsDraft();
		}

		return new DataResponse($data);
	}

	/**
	 * Get a poll
	 *
	 * @param int $pollId ID of the poll
	 * @psalm-param non-negative-int $pollId
	 * @return DataResponse<Http::STATUS_OK, TalkPoll, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Poll returned
	 * 404: Poll not found
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function showPoll(int $pollId): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\PollController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\PollController::class);
			return $proxy->showPoll($this->room, $this->participant, $pollId);
		}

		try {
			$poll = $this->pollService->getPoll($this->room->getId(), $pollId);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'poll'], Http::STATUS_NOT_FOUND);
		}

		if ($poll->getStatus() === Poll::STATUS_DRAFT && !$this->participant->hasModeratorPermissions()) {
			return new DataResponse(['error' => 'poll'], Http::STATUS_NOT_FOUND);
		}

		$votedSelf = $this->pollService->getVotesForActor($this->participant, $poll);
		$detailedVotes = [];
		if ($poll->getResultMode() === Poll::MODE_PUBLIC && $poll->getStatus() === Poll::STATUS_CLOSED) {
			$detailedVotes = $this->pollService->getVotes($poll);
		}

		return new DataResponse($this->renderPoll($poll, $votedSelf, $detailedVotes));
	}

	/**
	 * Vote on a poll
	 *
	 * @param int $pollId ID of the poll
	 * @psalm-param non-negative-int $pollId
	 * @param list<int> $optionIds IDs of the selected options
	 * @return DataResponse<Http::STATUS_OK, TalkPoll, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Voted successfully
	 * 400: Voting is not possible
	 * 404: Poll not found
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function votePoll(int $pollId, array $optionIds = []): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\PollController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\PollController::class);
			return $proxy->votePoll($this->room, $this->participant, $pollId, $optionIds);
		}

		try {
			$poll = $this->pollService->getPoll($this->room->getId(), $pollId);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'poll'], Http::STATUS_NOT_FOUND);
		}

		if ($poll->getStatus() === Poll::STATUS_DRAFT) {
			return new DataResponse(['error' => 'poll'], Http::STATUS_NOT_FOUND);
		}

		if ($poll->getStatus() === Poll::STATUS_CLOSED) {
			return new DataResponse(['error' => 'poll'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$votedSelf = $this->pollService->votePoll($this->participant, $poll, $optionIds);
		} catch (\RuntimeException $e) {
			return new DataResponse(['error' => 'options'], Http::STATUS_BAD_REQUEST);
		}

		if ($poll->getResultMode() === Poll::MODE_PUBLIC) {
			$attendee = $this->participant->getAttendee();
			try {
				$message = json_encode([
					'message' => 'poll_voted',
					'parameters' => [
						'poll' => [
							'type' => 'talk-poll',
							'id' => $poll->getId(),
							'name' => $poll->getQuestion(),
						],
					],
				], JSON_THROW_ON_ERROR);
				$this->chatManager->addSystemMessage($this->room, $this->participant, $attendee->getActorType(), $attendee->getActorId(), $message, $this->timeFactory->getDateTime(), false);
			} catch (\Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}

		return new DataResponse($this->renderPoll($poll, $votedSelf));
	}

	/**
	 * Close a poll
	 *
	 * @param int $pollId ID of the poll
	 * @psalm-param non-negative-int $pollId
	 * @return DataResponse<Http::STATUS_OK, TalkPoll, array{}>|DataResponse<Http::STATUS_ACCEPTED, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array{error: 'draft'|'options'|'poll'|'question'|'room'}, array{}>
	 *
	 * 200: Poll closed successfully
	 * 202: Poll draft was deleted successfully
	 * 400: Poll already closed
	 * 403: Missing permissions to close poll
	 * 404: Poll not found
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function closePoll(int $pollId): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\PollController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\PollController::class);
			return $proxy->closePoll($this->room, $this->participant, $pollId);
		}

		try {
			$poll = $this->pollService->getPoll($this->room->getId(), $pollId);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => PollPropertyException::REASON_POLL], Http::STATUS_NOT_FOUND);
		}

		if ($poll->getStatus() === Poll::STATUS_DRAFT) {
			if (!$this->participant->hasModeratorPermissions(false)) {
				// Only moderators can manage drafts
				return new DataResponse(['error' => PollPropertyException::REASON_POLL], Http::STATUS_NOT_FOUND);
			}

			$this->pollService->deleteByPollId($poll->getId());
			return new DataResponse(null, Http::STATUS_ACCEPTED);
		}

		if ($poll->getStatus() === Poll::STATUS_CLOSED) {
			return new DataResponse(['error' => PollPropertyException::REASON_POLL], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->pollService->closePoll($this->participant, $poll);
		} catch (WrongPermissionsException $e) {
			return new DataResponse(['error' => PollPropertyException::REASON_POLL], Http::STATUS_FORBIDDEN);
		}

		$attendee = $this->participant->getAttendee();
		try {
			$message = json_encode([
				'message' => 'poll_closed',
				'parameters' => [
					'poll' => [
						'type' => 'talk-poll',
						'id' => $poll->getId(),
						'name' => $poll->getQuestion(),
					],
				],
			], JSON_THROW_ON_ERROR);
			$this->chatManager->addSystemMessage($this->room, $this->participant, $attendee->getActorType(), $attendee->getActorId(), $message, $this->timeFactory->getDateTime(), true);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
		}

		$detailedVotes = [];
		if ($poll->getResultMode() === Poll::MODE_PUBLIC) {
			$detailedVotes = $this->pollService->getVotes($poll);
		}

		$votedSelf = $this->pollService->getVotesForActor($this->participant, $poll);

		return new DataResponse($this->renderPoll($poll, $votedSelf, $detailedVotes));
	}

	/**
	 * @return TalkPoll
	 * @throws JsonException
	 */
	protected function renderPoll(Poll $poll, array $votedSelf = [], array $detailedVotes = []): array {
		$data = $poll->renderAsPoll();

		$canSeeSummary = !empty($votedSelf) && $poll->getResultMode() === Poll::MODE_PUBLIC;

		if (!$canSeeSummary && $poll->getStatus() === Poll::STATUS_OPEN) {
			$data['votes'] = [];
			if ($this->participant->hasModeratorPermissions()
				|| ($poll->getActorType() === $this->participant->getAttendee()->getActorType()
					&& $poll->getActorId() === $this->participant->getAttendee()->getActorId())) {
				// Allow moderators and the author to see the number of voters,
				// So they know when to close the poll.
			} else {
				$data['numVoters'] = 0;
			}
		} elseif ($poll->getResultMode() === Poll::MODE_PUBLIC && $poll->getStatus() === Poll::STATUS_CLOSED) {
			$data['details'] = array_values(array_map(static fn (Vote $vote) => $vote->asArray(), $detailedVotes));
		}

		$data['votedSelf'] = array_values(array_map(static fn (Vote $vote) => $vote->getOptionId(), $votedSelf));

		return $data;
	}
}

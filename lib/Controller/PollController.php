<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Kate Döen <kate.doeen@nextcloud.com>
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

namespace OCA\Talk\Controller;

use JsonException;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Exceptions\WrongPermissionsException;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Middleware\Attribute\RequirePermission;
use OCA\Talk\Middleware\Attribute\RequireReadWriteConversation;
use OCA\Talk\Model\Poll;
use OCA\Talk\Model\Vote;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCA\Talk\Service\AttachmentService;
use OCA\Talk\Service\PollService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type SpreedPoll from ResponseDefinitions
 */
class PollController extends AEnvironmentAwareController {

	public function __construct(
		string $appName,
		IRequest $request,
		protected ChatManager $chatManager,
		protected PollService $pollService,
		protected AttachmentService $attachmentService,
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
	 * @param int $resultMode Mode how the results will be shown
	 * @param int $maxVotes Number of maximum votes per voter
	 * @return DataResponse<Http::STATUS_CREATED, SpreedPoll, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<empty>, array{}>
	 *
	 * 201: Poll created successfully
	 * 400: Creating poll is not possible
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	public function createPoll(string $question, array $options, int $resultMode, int $maxVotes): DataResponse {
		if ($this->room->getType() !== Room::TYPE_GROUP
			&& $this->room->getType() !== Room::TYPE_PUBLIC) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
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
				$maxVotes
			);
		} catch (\Exception $e) {
			$this->logger->error('Error creating poll', ['exception' => $e]);
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
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
			$this->chatManager->addSystemMessage($this->room, $attendee->getActorType(), $attendee->getActorId(), $message, $this->timeFactory->getDateTime(), true);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
		}

		return new DataResponse($this->renderPoll($poll, []), Http::STATUS_CREATED);
	}

	/**
	 * Get a poll
	 *
	 * @param int $pollId ID of the poll
	 * @return DataResponse<Http::STATUS_OK, SpreedPoll, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: Poll returned
	 * 404: Poll not found
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	public function showPoll(int $pollId): DataResponse {
		try {
			$poll = $this->pollService->getPoll($this->room->getId(), $pollId);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
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
	 * @param int[] $optionIds IDs of the selected options
	 * @return DataResponse<Http::STATUS_OK, SpreedPoll, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: Voted successfully
	 * 400: Voting is not possible
	 * 404: Poll not found
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	public function votePoll(int $pollId, array $optionIds = []): DataResponse {
		try {
			$poll = $this->pollService->getPoll($this->room->getId(), $pollId);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($poll->getStatus() === Poll::STATUS_CLOSED) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$votedSelf = $this->pollService->votePoll($this->participant, $poll, $optionIds);
		} catch (\RuntimeException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
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
				$this->chatManager->addSystemMessage($this->room, $attendee->getActorType(), $attendee->getActorId(), $message, $this->timeFactory->getDateTime(), false);
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
	 * @return DataResponse<Http::STATUS_OK, SpreedPoll, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array<empty>, array{}>
	 *
	 * 200: Poll closed successfully
	 * 400: Poll already closed
	 * 403: Missing permissions to close poll
	 * 404: Poll not found
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	public function closePoll(int $pollId): DataResponse {
		try {
			$poll = $this->pollService->getPoll($this->room->getId(), $pollId);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($poll->getStatus() === Poll::STATUS_CLOSED) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$poll->setStatus(Poll::STATUS_CLOSED);

		try {
			$this->pollService->updatePoll($this->participant, $poll);
		} catch (WrongPermissionsException $e) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return new DataResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
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
			$this->chatManager->addSystemMessage($this->room, $attendee->getActorType(), $attendee->getActorId(), $message, $this->timeFactory->getDateTime(), true);
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
	 * @return SpreedPoll
	 * @throws JsonException
	 */
	protected function renderPoll(Poll $poll, array $votedSelf = [], array $detailedVotes = []): array {
		$data = $poll->asArray();
		unset($data['roomId']);

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
			$data['details'] = array_map(static fn (Vote $vote) => $vote->asArray(), $detailedVotes);
		}

		$data['votedSelf'] = array_map(static fn (Vote $vote) => $vote->getOptionId(), $votedSelf);

		return $data;
	}
}

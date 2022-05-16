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

namespace OCA\Talk\Controller;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Exceptions\WrongPermissionsException;
use OCA\Talk\Model\Poll;
use OCA\Talk\Model\Vote;
use OCA\Talk\Service\PollService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class PollController extends AEnvironmentAwareController {
	protected ChatManager $chatManager;
	protected PollService $pollService;
	protected ITimeFactory $timeFactory;
	protected LoggerInterface $logger;

	public function __construct(string $appName,
								IRequest $request,
								ChatManager $chatManager,
								PollService $pollService,
								ITimeFactory $timeFactory,
								LoggerInterface $logger) {
		parent::__construct($appName, $request);
		$this->pollService = $pollService;
		$this->chatManager = $chatManager;
		$this->timeFactory = $timeFactory;
		$this->logger = $logger;
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequirePermissions(permissions=chat)
	 * @RequireModeratorOrNoLobby
	 *
	 * @param string $question
	 * @param array $options
	 * @param int $resultMode
	 * @param int $maxVotes
	 * @return DataResponse
	 */
	public function createPoll(string $question, array $options, int $resultMode, int $maxVotes): DataResponse {
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
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireModeratorOrNoLobby
	 *
	 * @param int $pollId
	 * @return DataResponse
	 */
	public function showPoll(int $pollId): DataResponse {
		try {
			$poll = $this->pollService->getPoll($this->room->getId(), $pollId);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$votes = $this->pollService->getVotesForActor($this->participant, $poll);

		return new DataResponse($this->renderPoll($poll, $votes));
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireModeratorOrNoLobby
	 *
	 * @param int $pollId
	 * @param int[] $optionIds
	 * @return DataResponse
	 */
	public function votePoll(int $pollId, array $optionIds): DataResponse {
		try {
			$poll = $this->pollService->getPoll($this->room->getId(), $pollId);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($poll->getMaxVotes() !== Poll::MAX_VOTES_UNLIMITED
			&& $poll->getMaxVotes() < count($optionIds)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$maxOptionId = max(array_keys(json_decode($poll->getOptions(), true, 512, JSON_THROW_ON_ERROR)));
		$maxVotedId = max($optionIds);
		$minVotedId = min($optionIds);
		if ($minVotedId < 0 || $maxVotedId > $maxOptionId) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$votes = $this->pollService->votePoll($this->participant, $poll, $optionIds);

		return new DataResponse($this->renderPoll($poll, $votes));
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireModeratorOrNoLobby
	 *
	 * @param int $pollId
	 * @return DataResponse
	 */
	public function closePoll(int $pollId): DataResponse {
		try {
			$poll = $this->pollService->getPoll($this->room->getId(), $pollId);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
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

		$votes = $this->pollService->getVotesForActor($this->participant, $poll);

		return new DataResponse($this->renderPoll($poll, $votes));
	}

	protected function renderPoll(Poll $poll, array $votes = []): array {
		$data = $poll->asArray();
		unset($data['roomId']);

		$data['voted'] = array_map(static fn (Vote $vote) => $vote->getOptionId(), $votes);

		return $data;
	}
}

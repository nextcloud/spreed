<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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

use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\Exceptions\ReactionAlreadyExistsException;
use OCA\Talk\Exceptions\ReactionNotSupportedException;
use OCA\Talk\Exceptions\ReactionOutOfContextException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\Comments\NotFoundException;
use OCP\IRequest;

class ReactionController extends AEnvironmentAwareController {
	/** @var ReactionManager */
	private $reactionManager;

	public function __construct(string $appName,
								IRequest $request,
								ReactionManager $reactionManager) {
		parent::__construct($appName, $request);
		$this->reactionManager = $reactionManager;
	}

	/**
	 * @NoAdminRequired
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequireModeratorOrNoLobby
	 *
	 * @param int $messageId for reaction
	 * @param string $reaction the reaction emoji
	 * @return DataResponse
	 */
	public function react(int $messageId, string $reaction): DataResponse {
		try {
			$this->reactionManager->addReactionMessage(
				$this->getRoom(),
				$this->getParticipant(),
				$messageId,
				$reaction
			);
			$status = Http::STATUS_CREATED;
		} catch (NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (ReactionAlreadyExistsException $e) {
			$status = Http::STATUS_OK;
		} catch (ReactionNotSupportedException | ReactionOutOfContextException | \Exception $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		$reactions = $this->reactionManager->retrieveReactionMessages($this->getRoom(), $this->getParticipant(), $messageId);
		return new DataResponse($reactions, $status);
	}

	/**
	 * @NoAdminRequired
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequireModeratorOrNoLobby
	 *
	 * @param int $messageId for reaction
	 * @param string $reaction the reaction emoji
	 * @return DataResponse
	 */
	public function delete(int $messageId, string $reaction): DataResponse {
		try {
			$this->reactionManager->deleteReactionMessage(
				$this->getRoom(),
				$this->getParticipant(),
				$messageId,
				$reaction
			);
			$reactions = $this->reactionManager->retrieveReactionMessages($this->getRoom(), $this->getParticipant(), $messageId);
		} catch (ReactionNotSupportedException | ReactionOutOfContextException | NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($reactions, Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequireModeratorOrNoLobby
	 *
	 * @param int $messageId for reaction
	 * @param string|null $reaction the reaction emoji
	 * @return DataResponse
	 */
	public function getReactions(int $messageId, ?string $reaction): DataResponse {
		try {
			// Verify that messageId is part of the room
			$this->reactionManager->getCommentToReact($this->getRoom(), (string) $messageId);
		} catch (ReactionNotSupportedException | ReactionOutOfContextException | NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$reactions = $this->reactionManager->retrieveReactionMessages($this->getRoom(), $this->getParticipant(), $messageId, $reaction);

		return new DataResponse($reactions, Http::STATUS_OK);
	}
}

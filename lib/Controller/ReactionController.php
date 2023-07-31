<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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

use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\Exceptions\ReactionAlreadyExistsException;
use OCA\Talk\Exceptions\ReactionNotSupportedException;
use OCA\Talk\Exceptions\ReactionOutOfContextException;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Middleware\Attribute\RequirePermission;
use OCA\Talk\Middleware\Attribute\RequireReadWriteConversation;
use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\Comments\NotFoundException;
use OCP\IRequest;

/**
 * @psalm-import-type SpreedReaction from ResponseDefinitions
 */
class ReactionController extends AEnvironmentAwareController {

	public function __construct(
		string $appName,
		IRequest $request,
		private ReactionManager $reactionManager,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Add a reaction to a message
	 *
	 * @param int $messageId ID of the message
	 * @param string $reaction Emoji to add
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_CREATED, array<string, SpreedReaction[]>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: Reaction already existed
	 * 201: Reaction added successfully
	 * 400: Adding reaction is not possible
	 * 404: Message not found
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	public function react(int $messageId, string $reaction): DataResponse {
		try {
			$this->reactionManager->addReactionMessage(
				$this->getRoom(),
				$this->getParticipant()->getAttendee()->getActorType(),
				$this->getParticipant()->getAttendee()->getActorId(),
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
	 * Delete a reaction from a message
	 *
	 * @param int $messageId ID of the message
	 * @param string $reaction Emoji to remove
	 * @return DataResponse<Http::STATUS_OK, array<string, SpreedReaction[]>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: Reaction deleted successfully
	 * 400: Deleting reaction is not possible
	 * 404: Message not found
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	public function delete(int $messageId, string $reaction): DataResponse {
		try {
			$this->reactionManager->deleteReactionMessage(
				$this->getRoom(),
				$this->getParticipant()->getAttendee()->getActorType(),
				$this->getParticipant()->getAttendee()->getActorId(),
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
	 * Get a list of reactions for a message
	 *
	 * @param int $messageId ID of the message
	 * @param string|null $reaction Emoji to filter
	 * @return DataResponse<Http::STATUS_OK, array<string, SpreedReaction[]>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: Reactions returned
	 * 404: Message or reaction not found
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
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

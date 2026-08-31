<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use Nextcloud\Matrix\Exception\MatrixException;
use Nextcloud\Matrix\Exception\TransportException;
use OCA\Talk\Matrix\Service\LifecycleService;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomFormatter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

/**
 * Room lifecycle from Talk: create Matrix rooms and DMs, join by address, browse the directory.
 *
 * @psalm-import-type TalkRoom from \OCA\Talk\ResponseDefinitions
 */
class MatrixRoomController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly LifecycleService $lifecycleService,
		private readonly RoomFormatter $roomFormatter,
		private readonly ParticipantService $participantService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Create a Matrix room (or a direct chat) and return the mirrored conversation
	 *
	 * @param string $name Room name (may be empty for direct chats)
	 * @param string $topic Room topic
	 * @param bool $encrypted Enable end-to-end encryption
	 * @param bool $public Publish in the room directory / allow anyone to join
	 * @param list<string> $inviteMatrixIds Matrix user ids (or localparts) to invite
	 * @param list<string> $inviteUserIds Nextcloud user ids to invite (their linked accounts)
	 * @param bool $direct Create a direct chat with the single invitee
	 * @return DataResponse<Http::STATUS_CREATED, TalkRoom, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
	 *
	 * 201: Room created
	 * 400: Invalid input
	 * 403: No active linked account / not allowed
	 * 502: Homeserver error
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 300)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/room', requirements: ['apiVersion' => '(v1)'])]
	public function create(string $name = '', string $topic = '', bool $encrypted = true, bool $public = false, array $inviteMatrixIds = [], array $inviteUserIds = [], bool $direct = false): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return new DataResponse(['error' => 'user'], Http::STATUS_FORBIDDEN);
		}
		try {
			$room = $this->lifecycleService->createRoom($user, $name, $topic, $encrypted, $public, array_values(array_filter($inviteMatrixIds, 'is_string')), array_values(array_filter($inviteUserIds, 'is_string')), $direct);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], $e->getMessage() === 'account' ? Http::STATUS_FORBIDDEN : Http::STATUS_BAD_REQUEST);
		} catch (TransportException) {
			return new DataResponse(['error' => 'unreachable'], Http::STATUS_BAD_GATEWAY);
		} catch (MatrixException $e) {
			return new DataResponse(['error' => $e->getMessage()], $e->getHttpStatus() >= 400 && $e->getHttpStatus() < 500 ? Http::STATUS_BAD_REQUEST : Http::STATUS_BAD_GATEWAY);
		}
		$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $user->getUID());
		return new DataResponse($this->roomFormatter->formatRoom($this->getResponseFormat(), [], $room, $participant), Http::STATUS_CREATED);
	}

	/**
	 * Join a Matrix room by id, alias, matrix.to link or matrix: URI (or start a chat with a matrix.to user link)
	 *
	 * @param string $reference Room reference
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{}>|DataResponse<Http::STATUS_ACCEPTED|Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
	 *
	 * 200: Joined, conversation returned
	 * 202: The room requires an invitation; a knock was sent
	 * 400: Not a room reference
	 * 403: No active linked account or joining is not allowed
	 * 404: Room not found
	 * 502: Homeserver error
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 300)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/room/join', requirements: ['apiVersion' => '(v1)'])]
	public function join(string $reference): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return new DataResponse(['error' => 'user'], Http::STATUS_FORBIDDEN);
		}
		try {
			$room = $this->lifecycleService->join($user, $reference);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], match ($e->getMessage()) {
				'account' => Http::STATUS_FORBIDDEN,
				'knocked' => Http::STATUS_ACCEPTED,
				'room' => Http::STATUS_NOT_FOUND,
				default => Http::STATUS_BAD_REQUEST,
			});
		} catch (\Nextcloud\Matrix\Exception\NotFoundException) {
			return new DataResponse(['error' => 'not-found'], Http::STATUS_NOT_FOUND);
		} catch (\Nextcloud\Matrix\Exception\ForbiddenException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (TransportException) {
			return new DataResponse(['error' => 'unreachable'], Http::STATUS_BAD_GATEWAY);
		} catch (MatrixException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}
		$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $user->getUID());
		return new DataResponse($this->roomFormatter->formatRoom($this->getResponseFormat(), [], $room, $participant));
	}

	/**
	 * Browse the public room directory of the linked homeserver
	 *
	 * @param string $search Search term
	 * @param ?string $since Pagination token
	 * @return DataResponse<Http::STATUS_OK, array{chunk: list<array{roomId: string, name: string, alias: ?string, topic: ?string, members: int, joined: bool}>, next_batch: ?string, total: ?int}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
	 *
	 * 200: Directory page returned
	 * 403: No active linked account
	 * 502: Homeserver error
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/matrix/room/directory', requirements: ['apiVersion' => '(v1)'])]
	public function directory(string $search = '', ?string $since = null): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return new DataResponse(['error' => 'user'], Http::STATUS_FORBIDDEN);
		}
		try {
			return new DataResponse($this->lifecycleService->publicRooms($user, $search, $since));
		} catch (\InvalidArgumentException) {
			return new DataResponse(['error' => 'account'], Http::STATUS_FORBIDDEN);
		} catch (MatrixException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}
	}
}

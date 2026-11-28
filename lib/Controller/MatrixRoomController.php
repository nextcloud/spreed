<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Exception\TransportException;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\DirectoryService;
use OCA\Talk\Matrix\Service\InvitationService;
use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-import-type TalkMatrixDirectoryRoom from ResponseDefinitions
 * @psalm-import-type TalkMatrixDirectoryUser from ResponseDefinitions
 */
class MatrixRoomController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ?string $userId,
		private readonly AccountService $accountService,
		private readonly InvitationService $invitationService,
		private readonly DirectoryService $directoryService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Search the public room directory of the homeserver of the linked Matrix account
	 *
	 * Spaces are left out, as they are not mirrored as conversations.
	 *
	 * @param string $search Matches name, topic and alias of the rooms, empty for all rooms
	 * @param int $limit Number of rooms per page, at most 50
	 * @param string $since Pagination token of the previous page
	 * @return DataResponse<Http::STATUS_OK, array{rooms: list<TalkMatrixDirectoryRoom>, nextBatch: ?string, total: ?non-negative-int}, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_TOO_MANY_REQUESTS|Http::STATUS_BAD_GATEWAY, array{error: 'account'|'matrix'|'ratelimited'|'unreachable'}, array{}>
	 *
	 * 200: Rooms of the directory, `nextBatch` is null on the last page. `total` is the estimate of the homeserver for its whole directory, which does not take the search term into account, or null when it has none
	 * 404: Matrix account not found
	 * 429: The Matrix homeserver rate limited the search
	 * 502: The Matrix homeserver could not be reached or rejected the search
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/matrix/directory/rooms', requirements: ['apiVersion' => '(v1)'])]
	public function searchRooms(string $search = '', int $limit = 20, string $since = ''): DataResponse {
		$account = $this->getAccount();
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}

		try {
			return new DataResponse($this->directoryService->searchRooms($account, $search, $limit, $since));
		} catch (MatrixException $e) {
			return $this->directoryError($e);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'matrix'], Http::STATUS_BAD_GATEWAY);
		}
	}

	/**
	 * Search users in the user directory of the homeserver of the linked Matrix account
	 *
	 * The homeserver decides which users it finds, e.g. Synapse only finds users who share a room with the searching user or are in a public room unless `user_directory.search_all_users` is enabled.
	 *
	 * @param string $search Name or Matrix user id
	 * @param int $limit Number of users, at most 50
	 * @return DataResponse<Http::STATUS_OK, array{users: list<TalkMatrixDirectoryUser>, limited: bool}, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_TOO_MANY_REQUESTS|Http::STATUS_BAD_GATEWAY, array{error: 'account'|'matrix'|'ratelimited'|'unreachable'}, array{}>
	 *
	 * 200: Users found, `limited` is true when the homeserver has more results
	 * 404: Matrix account not found
	 * 429: The Matrix homeserver rate limited the search
	 * 502: The Matrix homeserver could not be reached or rejected the search
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/matrix/directory/users', requirements: ['apiVersion' => '(v1)'])]
	public function searchUsers(string $search = '', int $limit = 10): DataResponse {
		$account = $this->getAccount();
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}

		try {
			return new DataResponse($this->directoryService->searchUsers($account, $search, $limit));
		} catch (MatrixException $e) {
			return $this->directoryError($e);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'matrix'], Http::STATUS_BAD_GATEWAY);
		}
	}

	/**
	 * Accept an invite to a Matrix room
	 *
	 * @param string $id ID of the invite
	 * @return DataResponse<Http::STATUS_OK, array{token: ?string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY, array{error: 'account'|'invitation'|'matrix'}, array{}>
	 *
	 * 200: Invite accepted, the token is null when the conversation is still created by another sync
	 * 404: Invite or Matrix account not found
	 * 502: The Matrix homeserver rejected joining the room or could not be reached
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/invite/{id}', requirements: ['apiVersion' => '(v1)', 'id' => '\d+'])]
	public function acceptInvite(string $id): DataResponse {
		$account = $this->getAccount();
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}

		try {
			$room = $this->invitationService->accept($account, $id);
		} catch (\InvalidArgumentException) {
			return new DataResponse(['error' => 'invitation'], Http::STATUS_NOT_FOUND);
		} catch (MatrixException|DoesNotExistException) {
			return new DataResponse(['error' => 'matrix'], Http::STATUS_BAD_GATEWAY);
		}
		return new DataResponse(['token' => $room?->getToken()]);
	}

	/**
	 * Decline an invite to a Matrix room
	 *
	 * @param string $id ID of the invite
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY, array{error: 'account'|'invitation'|'matrix'}, array{}>
	 *
	 * 200: Invite declined
	 * 404: Invite or Matrix account not found
	 * 502: The Matrix homeserver rejected declining or could not be reached
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/matrix/invite/{id}', requirements: ['apiVersion' => '(v1)', 'id' => '\d+'])]
	public function declineInvite(string $id): DataResponse {
		$account = $this->getAccount();
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}

		try {
			$this->invitationService->decline($account, $id);
		} catch (\InvalidArgumentException) {
			return new DataResponse(['error' => 'invitation'], Http::STATUS_NOT_FOUND);
		} catch (MatrixException|DoesNotExistException) {
			return new DataResponse(['error' => 'matrix'], Http::STATUS_BAD_GATEWAY);
		}
		return new DataResponse(null);
	}

	/**
	 * Create a Matrix room or a direct chat with a Matrix user
	 *
	 * @param string $name Name of the room, ignored for direct chats
	 * @param string $topic Topic of the room
	 * @param list<string> $invites Matrix user ids to invite
	 * @param bool $direct Create a direct chat with the only invited user
	 * @return DataResponse<Http::STATUS_CREATED, array{token: ?string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY, array{error: 'account'|'invite'|'matrix'|'name'}, array{}>
	 *
	 * 201: Room created, the token is null when the conversation is still created by another sync
	 * 400: Name or invited users are invalid
	 * 404: Matrix account not found
	 * 502: The Matrix homeserver rejected the room or could not be reached
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 10, period: 300)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/room', requirements: ['apiVersion' => '(v1)'])]
	public function createRoom(string $name = '', string $topic = '', array $invites = [], bool $direct = false): DataResponse {
		$account = $this->getAccount();
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}

		try {
			$room = $this->invitationService->create($account, $name, $topic, array_values(array_filter($invites, 'is_string')), $direct);
		} catch (\InvalidArgumentException $e) {
			/** @var 'invite'|'name' $error */
			$error = $e->getMessage();
			return new DataResponse(['error' => $error], Http::STATUS_BAD_REQUEST);
		} catch (MatrixException|DoesNotExistException) {
			return new DataResponse(['error' => 'matrix'], Http::STATUS_BAD_GATEWAY);
		}
		return new DataResponse(['token' => $room?->getToken()], Http::STATUS_CREATED);
	}

	/**
	 * Join a Matrix room by its address
	 *
	 * @param string $reference Room alias like `#room:example.org`, room id or a matrix.to link
	 * @return DataResponse<Http::STATUS_OK, array{token: ?string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY, array{error: 'account'|'matrix'|'reference'|'room'}, array{}>
	 *
	 * 200: Room joined, the token is null when the conversation is still created by another sync
	 * 400: The reference is not a Matrix room
	 * 404: Matrix account not found, or the room does not exist or can not be joined
	 * 502: The Matrix homeserver could not be reached
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 300)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/room/join', requirements: ['apiVersion' => '(v1)'])]
	public function joinRoom(string $reference): DataResponse {
		$account = $this->getAccount();
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}

		try {
			$room = $this->invitationService->join($account, $reference);
		} catch (\InvalidArgumentException) {
			return new DataResponse(['error' => 'reference'], Http::STATUS_BAD_REQUEST);
		} catch (MatrixException $e) {
			if (in_array($e->getErrcode(), ['M_FORBIDDEN', 'M_NOT_FOUND'], true)) {
				return new DataResponse(['error' => 'room'], Http::STATUS_NOT_FOUND);
			}
			return new DataResponse(['error' => 'matrix'], Http::STATUS_BAD_GATEWAY);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'matrix'], Http::STATUS_BAD_GATEWAY);
		}
		return new DataResponse(['token' => $room?->getToken()]);
	}

	/**
	 * @return DataResponse<Http::STATUS_TOO_MANY_REQUESTS|Http::STATUS_BAD_GATEWAY, array{error: 'matrix'|'ratelimited'|'unreachable'}, array{}>
	 */
	private function directoryError(MatrixException $e): DataResponse {
		if ($e->getHttpStatus() === Http::STATUS_TOO_MANY_REQUESTS) {
			return new DataResponse(['error' => 'ratelimited'], Http::STATUS_TOO_MANY_REQUESTS);
		}
		if ($e instanceof TransportException) {
			return new DataResponse(['error' => 'unreachable'], Http::STATUS_BAD_GATEWAY);
		}
		return new DataResponse(['error' => 'matrix'], Http::STATUS_BAD_GATEWAY);
	}

	protected function getAccount(): ?Account {
		$account = $this->userId !== null ? $this->accountService->getForUser($this->userId) : null;
		return $account?->getStatus() === Account::STATUS_ACTIVE ? $account : null;
	}
}

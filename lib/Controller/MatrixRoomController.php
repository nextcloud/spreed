<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\InvitationService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class MatrixRoomController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ?string $userId,
		private readonly AccountService $accountService,
		private readonly InvitationService $invitationService,
	) {
		parent::__construct($appName, $request);
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

	protected function getAccount(): ?Account {
		$account = $this->userId !== null ? $this->accountService->getForUser($this->userId) : null;
		return $account?->getStatus() === Account::STATUS_ACTIVE ? $account : null;
	}
}

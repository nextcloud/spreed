<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\Invitation;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\RoomFormatter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

/**
 * @psalm-import-type TalkFederationInvite from ResponseDefinitions
 * @psalm-import-type TalkRoom from ResponseDefinitions
 */
class FederationController extends OCSController {
	public function __construct(
		IRequest $request,
		private FederationManager $federationManager,
		private Manager $talkManager,
		private IUserSession $userSession,
		private RoomFormatter $roomFormatter,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @return 'json'|'xml'
	 */
	public function getResponseFormat(): string {
		return match ($this->request->getFormat()) {
			'json' => 'json',
			default => 'xml',
		};
	}

	/**
	 * Accept a federation invites
	 *
	 * 🚧 Draft: Still work in progress
	 *
	 * @param int $id ID of the share
	 * @psalm-param non-negative-int $id
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_GONE, array{error: string}, array{}>
	 *
	 * 200: Invite accepted successfully
	 * 400: Invite can not be accepted (maybe it was accepted already)
	 * 404: Invite can not be found
	 * 410: Remote server could not be reached to notify about the acceptance
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_FEDERATION)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/federation/invitation/{id}', requirements: [
		'apiVersion' => '(v1)',
		'id' => '[0-9]{1,64}',
	])]
	public function acceptShare(int $id): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return new DataResponse(['error' => 'user'], Http::STATUS_NOT_FOUND);
		}
		try {
			$participant = $this->federationManager->acceptRemoteRoomShare($user, $id);
		} catch (CannotReachRemoteException) {
			return new DataResponse(['error' => 'remote'], Http::STATUS_GONE);
		} catch (UnauthorizedException) {
			return new DataResponse(['error' => 'user'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], $e->getMessage() === 'invitation' ? Http::STATUS_NOT_FOUND : Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($this->roomFormatter->formatRoom(
			$this->getResponseFormat(),
			[],
			$participant->getRoom(),
			$participant,
		));
	}

	/**
	 * Decline a federation invites
	 *
	 * 🚧 Draft: Still work in progress
	 *
	 * @param int $id ID of the share
	 * @psalm-param non-negative-int $id
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Invite declined successfully
	 * 400: Invite was already accepted, use the "Remove the current user from a room" endpoint instead
	 * 404: Invite can not be found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_FEDERATION)]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/federation/invitation/{id}', requirements: [
		'apiVersion' => '(v1)',
		'id' => '[0-9]{1,64}',
	])]
	public function rejectShare(int $id): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return new DataResponse(['error' => 'user'], Http::STATUS_NOT_FOUND);
		}
		try {
			$this->federationManager->rejectRemoteRoomShare($user, $id);
		} catch (UnauthorizedException) {
			return new DataResponse(['error' => 'user'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], $e->getMessage() === 'invitation' ? Http::STATUS_NOT_FOUND : Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(null);
	}

	/**
	 * Get a list of federation invites
	 *
	 * 🚧 Draft: Still work in progress
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkFederationInvite>, array{}>
	 *
	 * 200: Get list of received federation invites successfully
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_FEDERATION)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/federation/invitation', requirements: [
		'apiVersion' => '(v1)',
	])]
	public function getShares(): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			throw new UnauthorizedException();
		}
		$invitations = $this->federationManager->getRemoteRoomShares($user);

		/** @var list<TalkFederationInvite> $data */
		$data = array_values(array_filter(array_map([$this, 'enrichInvite'], $invitations)));

		return new DataResponse($data);
	}

	/**
	 * @param Invitation $invitation
	 * @return TalkFederationInvite|null
	 */
	protected function enrichInvite(Invitation $invitation): ?array {
		try {
			$room = $this->talkManager->getRoomById($invitation->getLocalRoomId());
		} catch (RoomNotFoundException) {
			return null;
		}

		$federationInvite = $invitation->jsonSerialize();
		$federationInvite['roomName'] = $room->getName();
		$federationInvite['localToken'] = $room->getToken();
		return $federationInvite;
	}
}

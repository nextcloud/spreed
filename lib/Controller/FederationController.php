<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021, Gary Kim <gary@garykim.dev>
 *
 * @author Gary Kim <gary@garykim.dev>
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

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\Invitation;
use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\DB\Exception as DBException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

/**
 * @psalm-import-type TalkFederationInvite from ResponseDefinitions
 */
class FederationController extends OCSController {

	public function __construct(
		IRequest $request,
		private FederationManager $federationManager,
		private Manager $talkManager,
		private IUserSession $userSession,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Accept a federation invites
	 *
	 * @param int $id ID of the share
	 * @psalm-param non-negative-int $id
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 * @throws UnauthorizedException
	 * @throws DBException
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: Invite accepted successfully
	 */
	#[NoAdminRequired]
	public function acceptShare(int $id): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			throw new UnauthorizedException();
		}
		$this->federationManager->acceptRemoteRoomShare($user, $id);
		return new DataResponse();
	}

	/**
	 * Decline a federation invites
	 *
	 * @param int $id ID of the share
	 * @psalm-param non-negative-int $id
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 * @throws UnauthorizedException
	 * @throws DBException
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: Invite declined successfully
	 */
	#[NoAdminRequired]
	public function rejectShare(int $id): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			throw new UnauthorizedException();
		}
		$this->federationManager->rejectRemoteRoomShare($user, $id);
		return new DataResponse();
	}

	/**
	 * Get a list of federation invites
	 *
	 * @return DataResponse<Http::STATUS_OK, TalkFederationInvite[], array{}>
	 *
	 * 200: Get list of received federation invites successfully
	 */
	#[NoAdminRequired]
	public function getShares(): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			throw new UnauthorizedException();
		}
		$invitations = $this->federationManager->getRemoteRoomShares($user);

		/** @var TalkFederationInvite[] $data */
		$data = array_filter(array_map(function (Invitation $invitation): ?array {

			try {
				$this->talkManager->getRoomById($invitation->getLocalRoomId());
			} catch (RoomNotFoundException) {
				return null;
			}

			return $invitation->jsonSerialize();
		}, $invitations));

		return new DataResponse($data);
	}
}

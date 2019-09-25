<?php
declare(strict_types=1);

/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Spreed\Controller;

use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\FileInfo;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;

class PublicShareController extends OCSController {

	/** @var string|null */
	private $userId;
	/** @var IUserManager */
	private $userManager;
	/** @var ShareManager */
	private $shareManager;
	/** @var ISession */
	private $session;
	/** @var TalkSession */
	private $talkSession;
	/** @var Manager */
	private $manager;

	public function __construct(
			$appName,
			?string $UserId,
			IRequest $request,
			IUserManager $userManager,
			ShareManager $shareManager,
			ISession $session,
			TalkSession $talkSession,
			Manager $manager
	) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;
		$this->session = $session;
		$this->talkSession = $talkSession;
		$this->manager = $manager;
	}

	/**
	 * @PublicPage
	 * @UseSession
	 *
	 * Returns the token of the room associated to the file id of the given
	 * share token.
	 *
	 * This is the counterpart of FilesController::getRoom() for share tokens
	 * instead of file ids, although both return the same room token if the
	 * given file id and share token refer to the same file.
	 *
	 * If there is no room associated to the file id of the given share token a
	 * new room is created; the new room is a public room associated with a
	 * "file" object with the file id of the given share token. Unlike normal
	 * rooms in which the owner is the user that created the room these are
	 * special rooms without owner (although self joined users with direct
	 * access to the file become persistent participants automatically when they
	 * join until they explicitly leave or no longer have access to the file).
	 *
	 * In any case, to create or even get the token of the room, the file must
	 * be publicly shared (like a link share, for example); an error is returned
	 * otherwise.
	 *
	 * Besides the token of the room this also returns the current user ID and
	 * display name, if any; this is needed by the Talk sidebar to know the
	 * actual current user, as the public share page uses the incognito mode and
	 * thus logged in users as seen as guests.
	 *
	 * @param string $shareToken
	 * @return DataResponse the status code is "200 OK" if a room is returned,
	 *         or "404 Not found" if the given share token was invalid.
	 */
	public function getRoom(string $shareToken) {
		try {
			$share = $this->shareManager->getShareByToken($shareToken);
			if ($share->getPassword() !== null) {
				$shareId = $this->session->get('public_link_authenticated');
				if ($share->getId() !== $shareId) {
					throw new ShareNotFound();
				}
			}
		} catch (ShareNotFound $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($share->getNodeType() !== FileInfo::TYPE_FILE) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$fileId = (string)$share->getNodeId();

		try {
			$room = $this->manager->getRoomByObject('file', $fileId);
		} catch (RoomNotFoundException $e) {
			$name = $share->getNode()->getName();
			$room = $this->manager->createPublicRoom($name, 'file', $fileId);
		}

		$this->talkSession->setFileShareTokenForRoom($room->getToken(), $shareToken);

		$currentUser = $this->userManager->get($this->userId);
		$currentUserId = $currentUser instanceof IUser ? $currentUser->getUID() : '';
		$currentUserDisplayName = $currentUser instanceof IUser ? $currentUser->getDisplayName() : '';

		return new DataResponse([
			'token' => $room->getToken(),
			'userId' => $currentUserId,
			'userDisplayName' => $currentUserDisplayName,
		]);
	}

}

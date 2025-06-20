<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Files\Util;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\Files\FileInfo;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;

class FilesIntegrationController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private Manager $manager,
		private RoomService $roomService,
		private IShareManager $shareManager,
		private ISession $session,
		private IUserSession $userSession,
		private TalkSession $talkSession,
		private Util $util,
		private IConfig $config,
		private IL10N $l,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the token of the room associated to the given file id
	 *
	 * This is the counterpart of self::getRoomByShareToken() for file ids
	 * instead of share tokens, although both return the same room token if the
	 * given file id and share token refer to the same file.
	 *
	 * If there is no room associated to the given file id a new room is
	 * created; the new room is a public room associated with a "file" object
	 * with the given file id. Unlike normal rooms in which the owner is the
	 * user that created the room these are special rooms without owner
	 * (although self joined users with direct access to the file become
	 * persistent participants automatically when they join until they
	 * explicitly leave or no longer have access to the file).
	 *
	 * In any case, to create or even get the token of the room, the file must
	 * be shared and the user must be the owner of a public share of the file
	 * (like a link share, for example) or have direct access to that file; an
	 * error is returned otherwise. A user has direct access to a file if they
	 * have access to it (or to an ancestor) through a user, group, circle or
	 * room share (but not through a link share, for example), or if they are the
	 * owner of such a file.
	 *
	 * @param string $fileId ID of the file
	 * @return DataResponse<Http::STATUS_OK, array{token: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, null, array{}>
	 * @throws OCSNotFoundException Share not found
	 *
	 * 200: Room token returned
	 * 400: Rooms not allowed for shares
	 */
	#[NoAdminRequired]
	public function getRoomByFileId(string $fileId): DataResponse {
		if ($this->config->getAppValue('spreed', 'conversations_files', '1') !== '1') {
			return new DataResponse(null, Http::STATUS_BAD_REQUEST);
		}

		$currentUser = $this->userSession->getUser();
		if (!$currentUser instanceof IUser) {
			throw new OCSException($this->l->t('File is not shared, or shared but not with the user'), Http::STATUS_UNAUTHORIZED);
		}


		$node = $this->util->getAnyNodeOfFileAccessibleByUser($fileId, $currentUser->getUID());
		if ($node === null) {
			throw new OCSNotFoundException($this->l->t('File is not shared, or shared but not with the user'));
		}

		$users = $this->util->getUsersWithAccessFile($fileId);
		if (count($users) <= 1 && !$this->util->canGuestsAccessFile($fileId)) {
			throw new OCSNotFoundException($this->l->t('File is not shared, or shared but not with the user'));
		}

		try {
			$room = $this->manager->getRoomByObject('file', $fileId);
		} catch (RoomNotFoundException $e) {
			$name = $node->getName();
			$name = $this->roomService->prepareConversationName($name);
			$room = $this->roomService->createConversation(
				Room::TYPE_PUBLIC,
				$name,
				null,
				Room::OBJECT_TYPE_FILE,
				$fileId,
			);
		}

		return new DataResponse([
			'token' => $room->getToken()
		]);
	}

	/**
	 * Returns the token of the room associated to the file of the given
	 * share token
	 *
	 * This is the counterpart of self::getRoomByFileId() for share tokens
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
	 * thus logged-in users as seen as guests.
	 *
	 * @param string $shareToken Token of the file share
	 * @return DataResponse<Http::STATUS_OK, array{token: string, userId: string, userDisplayName: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Room token and user info returned
	 * 400: Rooms not allowed for shares
	 * 404: Share not found
	 */
	#[PublicPage]
	#[UseSession]
	#[BruteForceProtection(action: 'shareinfo')]
	public function getRoomByShareToken(string $shareToken): DataResponse {
		if ($this->config->getAppValue('spreed', 'conversations_files', '1') !== '1'
			|| $this->config->getAppValue('spreed', 'conversations_files_public_shares', '1') !== '1') {
			return new DataResponse(null, Http::STATUS_BAD_REQUEST);
		}

		try {
			$share = $this->shareManager->getShareByToken($shareToken);
			if ($share->getPassword() !== null) {
				$shareId = $this->session->get('public_link_authenticated');
				if ($share->getId() !== $shareId) {
					throw new ShareNotFound();
				}
			}
		} catch (ShareNotFound $e) {
			$response = new DataResponse(null, Http::STATUS_NOT_FOUND);
			$response->throttle(['token' => $shareToken, 'action' => 'shareinfo']);
			return $response;
		}

		try {
			if ($share->getNodeType() !== FileInfo::TYPE_FILE) {
				return new DataResponse(null, Http::STATUS_NOT_FOUND);
			}

			$fileId = (string)$share->getNodeId();

			try {
				$room = $this->manager->getRoomByObject('file', $fileId);
			} catch (RoomNotFoundException) {
				$name = $share->getNode()->getName();
				$name = $this->roomService->prepareConversationName($name);
				$room = $this->roomService->createConversation(
					Room::TYPE_PUBLIC,
					$name,
					null,
					Room::OBJECT_TYPE_FILE,
					$fileId,
				);
			}
		} catch (NotFoundException) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}

		$this->talkSession->setFileShareTokenForRoom($room->getToken(), $shareToken);

		$currentUser = $this->userSession->getUser();
		$currentUserId = $currentUser instanceof IUser ? $currentUser->getUID() : '';
		$currentUserDisplayName = $currentUser instanceof IUser ? $currentUser->getDisplayName() : '';

		return new DataResponse([
			'token' => $room->getToken(),
			'userId' => $currentUserId,
			'userDisplayName' => $currentUserDisplayName,
		]);
	}
}

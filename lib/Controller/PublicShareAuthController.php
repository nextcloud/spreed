<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;

class PublicShareAuthController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IUserManager $userManager,
		private IShareManager $shareManager,
		private IUserSession $userSession,
		private RoomService $roomService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Creates a new room for video verification (requesting the password of a share)
	 *
	 * The new room is a public room associated with a "share:password" object
	 * with the ID of the share token. Unlike normal rooms in which the owner is
	 * the user that created the room these are special rooms always created by
	 * a guest or user on behalf of a registered user, the sharer, who will be
	 * the owner of the room.
	 *
	 * The share must have "send password by Talk" enabled; an error is returned
	 * otherwise.
	 *
	 * @param string $shareToken Token of the file share
	 * @return DataResponse<Http::STATUS_CREATED, array{token: string, name: string, displayName: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 201: Room created successfully
	 * 404: Share not found
	 */
	#[PublicPage]
	#[OpenAPI(tags: ['files_integration'])]
	public function createRoom(string $shareToken): DataResponse {
		try {
			$share = $this->shareManager->getShareByToken($shareToken);
		} catch (ShareNotFound) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}

		if (!$share->getSendPasswordByTalk()) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}

		$sharerUser = $this->userManager->get($share->getSharedBy());

		if (!$sharerUser instanceof IUser) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}

		if ($share->getShareType() === IShare::TYPE_EMAIL) {
			$roomName = $share->getSharedWith();
		} else {
			$roomName = trim($share->getTarget(), '/');
		}
		$roomName = $this->roomService->prepareConversationName($roomName);

		// Create the room
		$room = $this->roomService->createConversation(
			Room::TYPE_PUBLIC,
			$roomName,
			$sharerUser,
			Room::OBJECT_TYPE_VIDEO_VERIFICATION,
			$shareToken,
		);

		$user = $this->userSession->getUser();
		$userId = $user instanceof IUser ? $user->getUID() : '';

		return new DataResponse([
			'token' => $room->getToken(),
			'name' => $room->getName(),
			'displayName' => $room->getDisplayName($userId),
		], Http::STATUS_CREATED);
	}
}

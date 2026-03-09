<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Config as TalkConfig;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Files\Util;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
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
		private TalkConfig $talkConfig,
		private IRootFolder $rootFolder,
		private ParticipantService $participantService,
		private ChatManager $chatManager,
		private ITimeFactory $timeFactory,
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
	 * @return DataResponse<Http::STATUS_OK, array{token: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Room token returned
	 * 400: Rooms not allowed for shares
	 * 401: User not logged in
	 * 404: File not accessible or not shared with enough users
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/{fileId}', requirements: [
		'apiVersion' => '(v1)',
		'fileId' => '.+',
	])]
	public function getRoomByFileId(string $fileId): DataResponse {
		if ($this->config->getAppValue('spreed', 'conversations_files', '1') !== '1') {
			return new DataResponse(['error' => $this->l->t('Rooms not allowed for shares')], Http::STATUS_BAD_REQUEST);
		}

		$currentUser = $this->userSession->getUser();
		if (!$currentUser instanceof IUser) {
			return new DataResponse(['error' => $this->l->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		$node = $this->util->getAnyNodeOfFileAccessibleByUser($fileId, $currentUser->getUID());
		if ($node === null) {
			return new DataResponse(['error' => $this->l->t('File not found or not accessible')], Http::STATUS_NOT_FOUND);
		}

		$users = $this->util->getUsersWithAccessFile($fileId, $currentUser->getUID());
		if (count($users) <= 1 && !$this->util->canGuestsAccessFile($fileId)) {
			return new DataResponse(['error' => $this->l->t('File not shared with enough users')], Http::STATUS_NOT_FOUND);
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
	 * @return DataResponse<Http::STATUS_OK, array{token: string, userId: string, userDisplayName: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Room token and user info returned
	 * 400: Rooms not allowed for shares
	 * 404: Share not found
	 */
	#[PublicPage]
	#[UseSession]
	#[BruteForceProtection(action: 'shareinfo')]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/publicshare/{shareToken}', requirements: [
		'apiVersion' => '(v1)',
		'shareToken' => '.+',
	])]
	public function getRoomByShareToken(string $shareToken): DataResponse {
		if ($this->config->getAppValue('spreed', 'conversations_files', '1') !== '1'
			|| $this->config->getAppValue('spreed', 'conversations_files_public_shares', '1') !== '1') {
			return new DataResponse(['error' => $this->l->t('Rooms not allowed for shares')], Http::STATUS_BAD_REQUEST);
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
			$response = new DataResponse(['error' => $this->l->t('Share not found')], Http::STATUS_NOT_FOUND);
			$response->throttle(['token' => $shareToken, 'action' => 'shareinfo']);
			return $response;
		}

		try {
			if ($share->getNodeType() !== FileInfo::TYPE_FILE) {
				return new DataResponse(['error' => $this->l->t('Share is not a file')], Http::STATUS_NOT_FOUND);
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
			return new DataResponse(['error' => $this->l->t('Shared file not found')], Http::STATUS_NOT_FOUND);
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

	/**
	 * Post a file from a conversation attachment folder as a chat message.
	 *
	 * The file must already be accessible to room members via a folder-level
	 * TYPE_ROOM share (created automatically by the conversation folder listener).
	 * This endpoint creates the chat message without adding a redundant per-file share.
	 *
	 * @param string $token Room token
	 * @param string $filePath File path relative to the user's root (e.g. "Talk/Room-abc/alice/photo.jpg")
	 * @param string $referenceId Client reference ID for the resulting chat message
	 * @param string $talkMetaData JSON-encoded metadata (caption, messageType, silent, …)
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY, array{error: string}, array{}>
	 *
	 * 200: File posted to chat
	 * 403: User is not allowed to post in this room or file is outside the conversation folder
	 * 404: Room not found or user is not a participant
	 * 422: File not found or not a regular file
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/room/{token}/file', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function shareConversationFile(string $token, string $filePath, string $referenceId = '', string $talkMetaData = ''): DataResponse {
		$currentUser = $this->userSession->getUser();
		if (!$currentUser instanceof IUser) {
			return new DataResponse(['error' => $this->l->t('User not logged in')], Http::STATUS_NOT_FOUND);
		}
		$userId = $currentUser->getUID();

		try {
			$room = $this->manager->getRoomForUserByToken($token, $userId);
		} catch (RoomNotFoundException) {
			return new DataResponse(['error' => $this->l->t('Room not found')], Http::STATUS_NOT_FOUND);
		}

		try {
			$participant = $this->participantService->getParticipant($room, $userId, false);
		} catch (ParticipantNotFoundException) {
			return new DataResponse(['error' => $this->l->t('User is not a participant')], Http::STATUS_NOT_FOUND);
		}

		if (!($participant->getPermissions() & Attendee::PERMISSIONS_CHAT)) {
			return new DataResponse(['error' => $this->l->t('No chat permission')], Http::STATUS_FORBIDDEN);
		}

		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			$node = $userFolder->get($filePath);
		} catch (NotFoundException) {
			return new DataResponse(['error' => $this->l->t('File not found')], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if ($node->getType() !== FileInfo::TYPE_FILE) {
			return new DataResponse(['error' => $this->l->t('Path is not a file')], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		// Validate the file is inside the expected conversation upload folder.
		// This prevents a user from posting arbitrary files as chat messages
		// without a per-file share being created.
		$expectedPrefix = $this->talkConfig->getConversationUploadFolder($userId, $room) . '/';
		if (!str_starts_with($filePath, $expectedPrefix)) {
			return new DataResponse(['error' => $this->l->t('File is outside the conversation folder')], Http::STATUS_FORBIDDEN);
		}

		$metaData = json_decode($talkMetaData, true);
		$metaData = is_array($metaData) ? $metaData : [];

		if (isset($metaData['messageType']) && $metaData['messageType'] === ChatManager::VERB_VOICE_MESSAGE && $node->getMimeType() !== 'audio/mpeg' && $node->getMimeType() !== 'audio/wav') {
			unset($metaData['messageType']);
		}
		$metaData['mimeType'] = $node->getMimeType();

		if (isset($metaData['caption'])) {
			if (is_string($metaData['caption']) && trim($metaData['caption']) !== '') {
				$metaData['caption'] = trim($metaData['caption']);
			} else {
				unset($metaData['caption']);
			}
		}

		$silent = (bool)($metaData[Message::METADATA_SILENT] ?? false);
		unset($metaData[Message::METADATA_SILENT]);

		$threadId = 0;
		if (isset($metaData['threadId'])) {
			$threadId = (int)$metaData['threadId'];
			unset($metaData['threadId']);
		}

		// replyTo and threadTitle require additional service lookups;
		// they are intentionally omitted in this initial implementation.
		unset($metaData['replyTo'], $metaData['threadTitle']);

		$message = json_encode([
			'message' => 'file_shared',
			'parameters' => [
				'file' => (string)$node->getId(),
				'metaData' => $metaData,
			],
		], JSON_THROW_ON_ERROR);

		try {
			$this->chatManager->addSystemMessage(
				$room,
				$participant,
				Attendee::ACTOR_USERS,
				$userId,
				$message,
				$this->timeFactory->getDateTime(),
				true,
				$referenceId !== '' ? $referenceId : null,
				null,
				false,
				$silent,
				$threadId,
			);
		} catch (\Exception) {
			return new DataResponse(['error' => $this->l->t('Failed to post chat message')], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		return new DataResponse([]);
	}

}

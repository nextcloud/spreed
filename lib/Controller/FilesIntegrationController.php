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
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
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
use OCP\Share\IShare;

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
	 * @return DataResponse<Http::STATUS_OK, array{token: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, null, array{}>
	 * @throws OCSNotFoundException Share not found
	 *
	 * 200: Room token returned
	 * 400: Rooms not allowed for shares
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/{fileId}', requirements: [
		'apiVersion' => '(v1)',
		'fileId' => '.+',
	])]
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

		$users = $this->util->getUsersWithAccessFile($fileId, $currentUser->getUID());
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
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/publicshare/{shareToken}', requirements: [
		'apiVersion' => '(v1)',
		'shareToken' => '.+',
	])]
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

	/**
	 * Post a file from a conversation attachment folder as a chat message.
	 *
	 * The file must already be inside the caller's conversation subfolder,
	 * which is shared with the room via a folder-level TYPE_ROOM share created
	 * automatically by ConversationFolderListener when the subfolder was first
	 * created via WebDAV MKCOL.  This endpoint creates the chat message without
	 * adding a redundant per-file share, keeping the Share Overview clean.
	 *
	 * @param string $token Room token
	 * @param string $filePath Path of the file relative to the user's home root
	 *                         (e.g. "Talk/Group Chat-abc123/Alice-alice/photo.jpg")
	 * @param string $referenceId Client-generated reference ID for the message
	 * @param string $talkMetaData JSON-encoded metadata (caption, messageType, silent, …)
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY, array{error: string}, array{}>
	 *
	 * 200: File posted as chat message
	 * 400: Rooms not allowed for file shares
	 * 403: User is not a participant or lacks chat permission
	 * 404: Room or file not found
	 * 422: File is not inside the expected conversation subfolder for this room
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/room/{token}/attachment', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]+',
	])]
	public function postAttachmentToRoom(string $token, string $filePath, string $referenceId, string $talkMetaData = ''): DataResponse {
		if ($this->config->getAppValue('spreed', 'conversations_files', '1') !== '1') {
			return new DataResponse(['error' => $this->l->t('Rooms not allowed for file shares')], Http::STATUS_BAD_REQUEST);
		}

		$currentUser = $this->userSession->getUser();
		if (!$currentUser instanceof IUser) {
			return new DataResponse(['error' => $this->l->t('User not logged in')], Http::STATUS_FORBIDDEN);
		}
		$uid = $currentUser->getUID();

		// Verify the room exists.
		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException) {
			return new DataResponse(['error' => $this->l->t('Conversation not found')], Http::STATUS_NOT_FOUND);
		}

		// Verify the caller is a participant with chat permissions.
		try {
			$participant = $this->participantService->getParticipant($room, $uid, false);
		} catch (ParticipantNotFoundException) {
			return new DataResponse(['error' => $this->l->t('Not a participant')], Http::STATUS_FORBIDDEN);
		}
		if (!($participant->getPermissions() & Attendee::PERMISSIONS_CHAT)) {
			return new DataResponse(['error' => $this->l->t('No chat permission')], Http::STATUS_FORBIDDEN);
		}

		// Look up the file in the caller's file tree.
		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
			$node = $userFolder->get($filePath);
		} catch (NotFoundException) {
			return new DataResponse(['error' => $this->l->t('File not found')], Http::STATUS_NOT_FOUND);
		}
		if ($node->getType() !== FileInfo::TYPE_FILE) {
			return new DataResponse(['error' => $this->l->t('Path must point to a file')], Http::STATUS_BAD_REQUEST);
		}

		// Validate the file is inside a properly structured conversation subfolder for this room.
		// Expected structure (relative to user home): <attachmentFolder>/<anything>-<token>/<anything>-<uid>/<file>
		// We validate structurally so display-name lookups (which may be empty in this context) are not needed.
		$attachmentFolderBase = ltrim($this->talkConfig->getAttachmentFolder($uid), '/');
		$pathParts = explode('/', ltrim($filePath, '/'));
		// Must have at least 4 parts: attachmentFolder / convFolder / subfolder / filename
		// (attachmentFolder may itself be multi-level, so count from the end)
		$count = count($pathParts);
		if ($count < 4) {
			return new DataResponse(['error' => $this->l->t('File is not inside a conversation folder shared with this room')], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		$convFolderSegment = $pathParts[$count - 3];
		$subFolderSegment = $pathParts[$count - 2];
		$attachmentPrefix = implode('/', array_slice($pathParts, 0, $count - 3));

		$validAttachmentFolder = $attachmentPrefix === $attachmentFolderBase;
		$validConvFolder = str_ends_with($convFolderSegment, '-' . $token);
		$validSubfolder = $subFolderSegment === $uid || str_ends_with($subFolderSegment, '-' . $uid);

		if (!$validAttachmentFolder || !$validConvFolder || !$validSubfolder) {
			return new DataResponse(['error' => $this->l->t('File is not inside a conversation folder shared with this room')], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		// Ensure the folder-level TYPE_ROOM share exists; create it lazily if not.
		$parentFolder = $node->getParent();
		$folderShares = $this->shareManager->getSharesBy($uid, IShare::TYPE_ROOM, $parentFolder, false, 50);
		$hasRoomShare = false;
		foreach ($folderShares as $folderShare) {
			if ($folderShare->getSharedWith() === $token) {
				$hasRoomShare = true;
				break;
			}
		}
		if (!$hasRoomShare) {
			$share = $this->shareManager->newShare();
			$share->setNode($parentFolder)
				->setShareType(IShare::TYPE_ROOM)
				->setSharedBy($uid)
				->setShareOwner($uid)
				->setSharedWith($token)
				->setPermissions(Constants::PERMISSION_READ)
				->setMailSend(false);
			$this->shareManager->createShare($share);
		}

		// Parse talkMetaData for caption, messageType, silent, replyTo, threadId.
		$metaData = json_decode($talkMetaData, true);
		$metaData = is_array($metaData) ? $metaData : [];

		// Validate and sanitize messageType.
		if (isset($metaData['messageType']) && $metaData['messageType'] === ChatManager::VERB_VOICE_MESSAGE) {
			$mime = $node->getMimeType();
			if ($mime !== 'audio/mpeg' && $mime !== 'audio/wav') {
				unset($metaData['messageType']);
			}
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
		$replyToId = isset($metaData['replyTo']) ? (int)$metaData['replyTo'] : null;
		$threadId = isset($metaData['threadId']) ? (int)$metaData['threadId'] : 0;
		unset($metaData['replyTo'], $metaData['threadId'], $metaData[Message::METADATA_SILENT]);

		$replyToComment = null;
		if ($replyToId !== null) {
			try {
				$replyToComment = $this->chatManager->getComment($room, (string)$replyToId);
			} catch (\Exception) {
				// Invalid replyTo — ignore.
			}
		}

		// Create the file_shared system message referencing the file by node ID.
		// The parameters use 'fileId' instead of 'share' so no per-file TYPE_ROOM
		// share is needed; access is controlled by the folder-level share.
		$this->chatManager->addSystemMessage(
			$room,
			$participant,
			Attendee::ACTOR_USERS,
			$uid,
			json_encode(['message' => 'file_shared', 'parameters' => ['fileId' => (string)$node->getId(), 'metaData' => $metaData]]),
			$this->timeFactory->getDateTime(),
			true,
			$referenceId !== '' ? $referenceId : null,
			$replyToComment,
			false,
			$silent,
			$threadId,
		);

		return new DataResponse([], Http::STATUS_OK);
	}
}

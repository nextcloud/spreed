<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use Nextcloud\Matrix\Crypto\Attachment;
use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Exception\MatrixException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\EventMap;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\CryptoService;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Proxies Matrix media for attendees of the conversation the media was posted
 * in. Downloads go through the requester's *own* homeserver – never to the
 * origin server directly. Encrypted attachments are decrypted here.
 */
class MatrixMediaController extends Controller {
	private const INLINE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/avif', 'video/mp4', 'video/webm', 'audio/mpeg', 'audio/ogg', 'audio/mp4', 'audio/webm', 'application/pdf', 'text/plain'];
	private const MAX_ENCRYPTED_BYTES = 100 * 1024 * 1024;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly AccountService $accountService,
		private readonly EventMapMapper $eventMapMapper,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly Manager $manager,
		private readonly ParticipantService $participantService,
		private readonly ICommentsManager $commentsManager,
		private readonly CryptoService $cryptoService,
		private readonly IRootFolder $rootFolder,
		private readonly \OCA\Talk\Matrix\Model\MatrixMemberMapper $memberMapper,
		private readonly \OCA\Talk\Service\AvatarService $avatarService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Download a Matrix attachment by the event id it was posted with (`?thumbnail=1` for a preview)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 300, period: 60)]
	#[FrontpageRoute(verb: 'GET', url: '/matrix/media/{eventId}', requirements: ['eventId' => '.+'])]
	public function media(string $eventId): Response {
		$fetched = $this->fetch($eventId, $this->request->getParam('thumbnail') === '1');
		if ($fetched instanceof DataResponse) {
			return $fetched;
		}
		$response = new StreamResponse($fetched['stream']);
		$response->addHeader('Content-Type', $fetched['inline'] ? $fetched['contentType'] : 'application/octet-stream');
		$response->addHeader('Content-Disposition', ($fetched['inline'] ? 'inline' : 'attachment') . '; filename="' . addslashes($fetched['name']) . '"');
		$response->addHeader('Cache-Control', 'private, max-age=86400');
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->addHeader('Content-Security-Policy', "default-src 'none'; sandbox");
		if ($fetched['length'] !== null) {
			$response->addHeader('Content-Length', (string)$fetched['length']);
		}
		return $response;
	}

	/**
	 * Avatar of a Matrix-only member of a conversation (proxied through the requester's homeserver)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 600, period: 60)]
	#[FrontpageRoute(verb: 'GET', url: '/matrix/avatar/{token}/{size}/{mxid}', requirements: ['token' => '[a-z0-9]{4,30}', 'size' => '(64|512)', 'mxid' => '.+'])]
	public function memberAvatar(string $token, int $size, string $mxid, bool $darkTheme = false): Response {
		$user = $this->userSession->getUser();
		$fallback = function () use ($darkTheme): Response {
			$file = $this->avatarService->getPersonPlaceholder($darkTheme);
			$response = new \OCP\AppFramework\Http\FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => $file->getMimeType()]);
			$response->cacheFor(300);
			return $response;
		};
		if ($user === null) {
			return $fallback();
		}
		try {
			$room = $this->manager->getRoomByToken($token, $user->getUID());
			$this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $user->getUID());
			$matrixRoom = $this->roomMapper->getByRoomId($room->getId());
			$member = $this->memberMapper->get($matrixRoom->getMatrixRoomId(), rawurldecode($mxid));
		} catch (\Throwable) {
			return $fallback();
		}
		$avatar = $member->getAvatarUrl();
		$account = $this->accountService->getForUser($user->getUID());
		if ($avatar === null || $account === null || !$account->isActive()) {
			return $fallback();
		}
		try {
			$upstream = $this->accountService->client($account, 20)->downloadThumbnail($avatar, $size, $size, 'crop');
		} catch (\Throwable) {
			return $fallback();
		}
		$stream = $upstream->getBody()->detach();
		if (!is_resource($stream)) {
			return $fallback();
		}
		$response = new StreamResponse($stream);
		$contentType = strtolower(trim(explode(';', $upstream->getHeaderLine('Content-Type'))[0]));
		$response->addHeader('Content-Type', str_starts_with($contentType, 'image/') ? $contentType : 'image/png');
		$response->addHeader('Cache-Control', 'private, max-age=86400');
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		return $response;
	}

	/**
	 * Copy a Matrix attachment into the user's files ("Save to Nextcloud")
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	#[FrontpageRoute(verb: 'POST', url: '/matrix/media/{eventId}/save', requirements: ['eventId' => '.+'])]
	public function save(string $eventId, string $folder = ''): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'user'], Http::STATUS_UNAUTHORIZED);
		}
		$fetched = $this->fetch($eventId, false);
		if ($fetched instanceof DataResponse) {
			return $fetched;
		}
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$targetFolder = $userFolder;
		$folder = trim($folder, '/');
		if ($folder !== '') {
			try {
				$targetFolder = $userFolder->get($folder);
			} catch (\OCP\Files\NotFoundException) {
				$targetFolder = $userFolder->newFolder($folder);
			}
			if (!$targetFolder instanceof Folder) {
				return new DataResponse(['error' => 'folder'], Http::STATUS_BAD_REQUEST);
			}
		}
		$name = $fetched['name'] !== '' ? $fetched['name'] : 'matrix-file';
		$file = $targetFolder->newFile($targetFolder->getNonExistingName($name), stream_get_contents($fetched['stream']));
		return new DataResponse(['path' => $userFolder->getRelativePath($file->getPath()), 'fileId' => $file->getId()], Http::STATUS_CREATED);
	}

	/**
	 * Resolve, authorise, download and (if needed) decrypt an attachment.
	 *
	 * @return array{stream: resource, contentType: string, inline: bool, name: string, length: ?int}|DataResponse
	 */
	private function fetch(string $eventId, bool $wantThumbnail): array|DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}
		$map = $this->eventMapMapper->findByEventId($eventId);
		if ($map === null || $map->getCommentId() === null) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		try {
			$matrixRoom = $this->roomMapper->getByMatrixRoomId($map->getMatrixRoomId());
			$room = $this->manager->getRoomById($matrixRoom->getRoomId());
			$this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $user->getUID());
			$comment = $this->commentsManager->get((string)$map->getCommentId());
		} catch (DoesNotExistException|RoomNotFoundException|ParticipantNotFoundException|NotFoundException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$data = json_decode($comment->getMessage(), true);
		$object = is_array($data) ? ($data['parameters']['metaData'] ?? $data['parameters']['object'] ?? null) : null;
		if (!is_array($object) || ($object['type'] ?? '') !== 'matrix-media' || ($object['mxc'] ?? '') === '') {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$account = $this->accountService->getForUser($user->getUID());
		if ($account === null || !$account->isActive()) {
			return new DataResponse(['error' => 'account'], Http::STATUS_FORBIDDEN);
		}
		$encrypted = ($object['encrypted'] ?? '0') === '1';
		$name = basename((string)($object['name'] ?? 'file'));

		try {
			$client = $this->accountService->client($account, 60);
			if ($wantThumbnail && !$encrypted) {
				// Server-side thumbnail (or the sender-provided one) for unencrypted images
				try {
					$upstream = $client->downloadThumbnail((string)($object['thumbnail-mxc'] ?? $object['mxc']), 800, 600, 'scale');
				} catch (MatrixException) {
					$upstream = $client->downloadMedia((string)$object['mxc']);
				}
			} else {
				$upstream = $client->downloadMedia((string)$object['mxc']);
			}
		} catch (MatrixException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}

		if ($encrypted) {
			$file = $this->encryptedFileInfo($map, $account, $matrixRoom->getMatrixRoomId());
			if ($file === null) {
				return new DataResponse(['error' => 'no-key'], Http::STATUS_NOT_FOUND);
			}
			$ciphertext = (string)$upstream->getBody();
			if (strlen($ciphertext) > self::MAX_ENCRYPTED_BYTES) {
				return new DataResponse(['error' => 'too-large'], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
			}
			try {
				$plain = Attachment::decrypt($file, $ciphertext);
			} catch (CryptoException $e) {
				return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
			}
			$contentType = strtolower((string)($object['mimetype'] ?? 'application/octet-stream'));
			$stream = fopen('php://temp', 'w+');
			fwrite($stream, $plain);
			rewind($stream);
			return ['stream' => $stream, 'contentType' => $contentType, 'inline' => in_array($contentType, self::INLINE_TYPES, true), 'name' => $name, 'length' => strlen($plain)];
		}

		$contentType = strtolower(trim(explode(';', $upstream->getHeaderLine('Content-Type'))[0]));
		$stream = $upstream->getBody()->detach();
		if (!is_resource($stream)) {
			$stream = fopen('php://temp', 'w+');
			fwrite($stream, (string)$upstream->getBody());
			rewind($stream);
		}
		return [
			'stream' => $stream,
			'contentType' => $contentType,
			'inline' => in_array($contentType, self::INLINE_TYPES, true),
			'name' => $name,
			'length' => $upstream->hasHeader('Content-Length') ? (int)$upstream->getHeaderLine('Content-Length') : null,
		];
	}

	/**
	 * The EncryptedFile object of an attachment: decrypt the original event again.
	 * @return array<string, mixed>|null
	 */
	private function encryptedFileInfo(EventMap $map, Account $account, string $matrixRoomId): ?array {
		try {
			$event = $this->accountService->client($account, 20)->getEvent($matrixRoomId, $map->getEventId());
			if ($event->type !== 'm.room.encrypted') {
				return is_array($event->content['file'] ?? null) ? $event->content['file'] : null;
			}
			$decrypted = $this->cryptoService->decryptRoomEvent($account, $matrixRoomId, $event->sender, $event->content);
			return is_array($decrypted['content']['file'] ?? null) ? $decrypted['content']['file'] : null;
		} catch (\Throwable) {
			return null;
		}
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\IRequest;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class MatrixMediaController extends Controller {
	/** Types the browser may show, everything else is downloaded */
	public const INLINE_TYPES = ['image/gif', 'image/jpeg', 'image/png', 'image/webp'];
	public const PREVIEW_SIZE = 640;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ?string $userId,
		private readonly AccountService $accountService,
		private readonly EventMapMapper $eventMapMapper,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly Manager $manager,
		private readonly ParticipantService $participantService,
		private readonly ICommentsManager $commentsManager,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Download an attachment of a Matrix conversation through the homeserver of the user
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 120, period: 60)]
	#[FrontpageRoute(verb: 'GET', url: '/matrix/media/{id}', requirements: ['id' => '\d+'])]
	public function download(string $id): Response {
		return $this->proxy($id, false);
	}

	/**
	 * Scaled down image attachment of a Matrix conversation
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 300, period: 60)]
	#[FrontpageRoute(verb: 'GET', url: '/matrix/media/{id}/preview', requirements: ['id' => '\d+'])]
	public function preview(string $id): Response {
		return $this->proxy($id, true);
	}

	protected function proxy(string $id, bool $preview): Response {
		$attachment = $this->getAttachment($id);
		$account = $this->userId !== null ? $this->accountService->getForUser($this->userId) : null;
		if ($attachment === null || $account?->getStatus() !== Account::STATUS_ACTIVE) {
			return new Response(Http::STATUS_NOT_FOUND);
		}

		try {
			$client = $this->accountService->getClient($account);
			$upstream = $preview
				? $client->downloadThumbnail($attachment['mxc'], self::PREVIEW_SIZE, false)
				: $client->downloadMedia($attachment['mxc']);
		} catch (MatrixException $e) {
			return new Response($e->getErrcode() === 'M_NOT_FOUND' ? Http::STATUS_NOT_FOUND : Http::STATUS_BAD_GATEWAY);
		} catch (\InvalidArgumentException|DoesNotExistException) {
			return new Response(Http::STATUS_NOT_FOUND);
		}

		$contentType = strtolower(trim(explode(';', $upstream->getHeaderLine('Content-Type'))[0]));
		$inline = in_array($contentType, self::INLINE_TYPES, true);
		if ($preview && !$inline) {
			return new Response(Http::STATUS_NOT_FOUND);
		}
		$stream = $upstream->getBody()->detach();
		if (!is_resource($stream)) {
			return new Response(Http::STATUS_BAD_GATEWAY);
		}
		$fileName = str_replace(['"', '\\', "\r", "\n"], '_', $attachment['name']);

		$response = new StreamResponse($stream);
		$response->addHeader('Content-Type', $inline ? $contentType : 'application/octet-stream');
		$response->addHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $fileName . '"');
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->addHeader('Content-Security-Policy', "default-src 'none'; sandbox");
		$response->cacheFor(86400, false);
		return $response;
	}

	/**
	 * Content URI and name of the attachment when the user is in the conversation
	 *
	 * @return array{mxc: string, name: string}|null
	 */
	protected function getAttachment(string $id): ?array {
		if ($this->userId === null) {
			return null;
		}

		try {
			$eventMap = $this->eventMapMapper->getById($id);
			$room = $this->manager->getRoomById($this->roomMapper->getById($eventMap->getMatrixRoomId())->getRoomId());
			$this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $this->userId);
			$comment = $this->commentsManager->get((string)$eventMap->getCommentId());
		} catch (DoesNotExistException|RoomNotFoundException|ParticipantNotFoundException|NotFoundException) {
			return null;
		}

		$data = json_decode($comment->getMessage(), true);
		$object = is_array($data) ? ($data['parameters']['metaData'] ?? null) : null;
		if (!is_array($object) || !is_string($object['mxc'] ?? null) || $comment->getObjectId() !== (string)$room->getId()) {
			return null;
		}
		return ['mxc' => $object['mxc'], 'name' => (string)($object['name'] ?? '')];
	}
}

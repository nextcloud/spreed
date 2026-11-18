<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Exceptions\RoomProperty\UploadsWithoutStorageException;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\RoomFormatter;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\UploadsWithoutStorageService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\NotFoundException;
use OCP\IRequest;

/**
 * @psalm-import-type TalkRoom from ResponseDefinitions
 */
class UploadsWithoutStorageController extends AEnvironmentAwareOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly RoomService $roomService,
		private readonly RoomFormatter $roomFormatter,
		private readonly UploadsWithoutStorageService $uploadsWithoutStorageService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Allow or stop uploads of guests, email guests and users with a quota of 0
	 *
	 * Their files are stored in the Guests folder of the moderator who allowed it.
	 *
	 * Required capability: `uploads-without-storage`
	 *
	 * @param bool $allowed Whether participants without storage may upload files into the current user's storage
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'breakout-room'|'federated'|'matrix'|'storage'|'type'|'unavailable'}, array{}>
	 *
	 * 200: Setting updated successfully
	 * 400: Updating the setting is not possible
	 */
	#[NoAdminRequired]
	#[RequireModeratorParticipant]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/room/{token}/uploads-without-storage', requirements: [
		'apiVersion' => '(v4)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function setUploadsWithoutStorage(bool $allowed): DataResponse {
		try {
			if ($allowed) {
				$this->uploadsWithoutStorageService->allowUploads($this->participant, $this->room);
			} else {
				$this->roomService->setGuestsFolderId($this->room, 0);
			}
		} catch (UploadsWithoutStorageException $e) {
			return new DataResponse(['error' => $e->getReason()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($this->roomFormatter->formatRoom(
			$this->getResponseFormat(),
			[],
			$this->room,
			$this->participant,
		));
	}

	/**
	 * Get the Guests folder that stores uploads of participants without storage
	 *
	 * Required capability: `uploads-without-storage`
	 *
	 * @return DataResponse<Http::STATUS_OK, array{owner: string, path: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Display name of the moderator and the path of the folder in their files
	 * 404: Uploads without storage are off
	 */
	#[NoAdminRequired]
	#[RequireModeratorParticipant]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/room/{token}/uploads-without-storage', requirements: [
		'apiVersion' => '(v4)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function getUploadsWithoutStorage(): DataResponse {
		try {
			return new DataResponse($this->uploadsWithoutStorageService->getGuestsFolderLocation($this->room));
		} catch (NotFoundException) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}
	}
}

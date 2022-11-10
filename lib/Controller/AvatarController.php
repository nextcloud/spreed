<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022, Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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

use InvalidArgumentException;
use OC\Files\Filesystem;
use OCA\Talk\Service\AvatarService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class AvatarController extends AEnvironmentAwareController {
	private IAppData $appData;
	private AvatarService $avatarService;
	private IUserSession $userSession;
	private IL10N $l;

	public function __construct(
		string $appName,
		IRequest $request,
		IAppData $appData,
		AvatarService $avatarService,
		IUserSession $userSession,
		IL10N $l10n
	) {
		parent::__construct($appName, $request);
		$this->appData = $appData;
		$this->avatarService = $avatarService;
		$this->userSession = $userSession;
		$this->l = $l10n;
	}

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 */
	public function uploadAvatar(): DataResponse {
		$file = $this->request->getUploadedFile('file');

		if (is_null($file)) {
			throw new InvalidArgumentException($this->l->t('No image file provided'));
		}

		if (
			$file['error'] === 0 &&
			is_uploaded_file($file['tmp_name']) &&
			!Filesystem::isFileBlacklisted($file['tmp_name'])
		) {
			if ($file['size'] > 20 * 1024 * 1024) {
				return new DataResponse(
					['message' => $this->l->t('File is too big')],
					Http::STATUS_BAD_REQUEST
				);
			}
			$content = file_get_contents($file['tmp_name']);
			unlink($file['tmp_name']);
		} else {
			throw new InvalidArgumentException($this->l->t('Invalid file provided'));
		}

		try {
			$image = new \OC_Image();
			$image->loadFromData($content);
			$image->readExif($content);
			$image->fixOrientation();
			if (!($image->height() === $image->width())) {
				throw new InvalidArgumentException($this->l->t('Avatar image is not square'));
			}

			if (!$image->valid()) {
				throw new InvalidArgumentException($this->l->t('Invalid image'));
			}

			$mimeType = $image->mimeType();
			$allowedMimeTypes = [
				'image/jpeg',
				'image/png',
				'image/svg',
			];
			if (!in_array($mimeType, $allowedMimeTypes)) {
				throw new InvalidArgumentException($this->l->t('Unknown filetype'));
			}

			try {
				$folder = $this->appData->getFolder('room-avatar');
			} catch (NotFoundException $e) {
				$folder = $this->appData->newFolder('room-avatar');
			}
			$token = $this->getRoom()->getToken();
			$folder->newFile($token, $image->data());
			return new DataResponse();
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			$this->logger->error('Failed to post avatar', [
				'exception' => $e,
			]);

			return new DataResponse(['message' => $this->l->t('An error occurred. Please contact your administrator.')], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 */
	public function getAvatar(bool $dark = false): Response {
		$file = $this->avatarService->getAvatar($this->getRoom(), $this->userSession->getUser(), $dark);

		$response = new FileDisplayResponse($file);
		$response->addHeader('Content-Type', $file->getMimeType());
		// Cache for 1 day
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 */
	public function getAvatarDark(): Response {
		return $this->getAvatar(true);
	}

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 */
	public function deleteAvatar(): DataResponse {
		$this->avatarService->deleteAvatar($this->getRoom());
		return new DataResponse([]);
	}
}

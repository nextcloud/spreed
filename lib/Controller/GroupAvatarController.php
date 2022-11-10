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

use OC\Files\Filesystem;
use OC\NotSquareException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IRequest;

class GroupAvatarController extends AEnvironmentAwareController {
	private IAppData $appData;
	private IL10N $l;

	public function __construct(
		string $appName,
		IRequest $request,
		IAppData $appData,
		IL10N $l10n
	) {
		parent::__construct($appName, $request);
		$this->appData = $appData;
		$this->l = $l10n;
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireModeratorOrNoLobby
	 */
	public function postAvatar(): DataResponse {
		$file = $this->request->getUploadedFile('file');

		if (is_null($file)) {
			return new DataResponse(
				['message' => $this->l->t('No image file provided')],
				Http::STATUS_BAD_REQUEST
			);
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
			return new DataResponse(
				['message' => $this->l->t('Invalid file provided')],
				Http::STATUS_BAD_REQUEST
			);
		}

		try {
			$image = new \OC_Image();
			$image->loadFromData($content);
			$image->readExif($content);
			$image->fixOrientation();
			if (!($image->height() === $image->width())) {
				throw new NotSquareException($this->l->t('Avatar image is not square'));
			}

			if (!$image->valid()) {
				return new DataResponse(
					['data' => ['message' => $this->l->t('Invalid image')]],
					Http::STATUS_BAD_REQUEST
				);
			}

			$mimeType = $image->mimeType();
			if ($mimeType !== 'image/jpeg' && $mimeType !== 'image/png') {
				return new DataResponse(
					['data' => ['message' => $this->l->t('Unknown filetype')]],
					Http::STATUS_BAD_REQUEST
				);
			}

			try {
				$folder = $this->appData->getFolder('room-avatar');
			} catch (NotFoundException $e) {
				$folder = $this->appData->newFolder('room-avatar');
			}
			$token = $this->getRoom()->getToken();
			$extension = explode('/', $mimeType)[1];
			$folder->newFile($token . '.' . $extension, $image->data());
			return new DataResponse();
		} catch (NotSquareException $e) {
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
	 * @RequireModeratorOrNoLobby
	 */
	public function getAvatar(): Response {
		try {
			$folder = $this->appData->getFolder('room-avatar');
		} catch (NotFoundException $e) {
			$folder = $this->appData->newFolder('room-avatar');
		}
		$token = $this->getRoom()->getToken();
		if ($folder->fileExists($token . '.png')) {
			$file = $folder->getFile($token . '.png');
		} elseif ($folder->fileExists($token . '.jpeg')) {
			$file = $folder->getFile($token . '.jpeg');
		} else {
			// @todo Implement fallback images
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$resp = new FileDisplayResponse($file);
		$resp->addHeader('Content-Type', $file->getMimeType());
		return $resp;
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireModeratorOrNoLobby
	 */
	public function deleteAvatar(): DataResponse {
		try {
			$folder = $this->appData->getFolder('room-avatar');
		} catch (NotFoundException $e) {
			$folder = $this->appData->newFolder('room-avatar');
		}
		$token = $this->getRoom()->getToken();
		$folder->delete($token . '.png');
		$folder->delete($token . '.jpeg');
		return new DataResponse([]);
	}
}

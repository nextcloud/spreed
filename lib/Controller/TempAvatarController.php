<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OC\Files\Filesystem;
use OC\NotSquareException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IAvatarManager;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TempAvatarController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IAvatarManager $avatarManager,
		private IL10N $l,
		private LoggerInterface $logger,
		private string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Upload your avatar as a user
	 *
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: Avatar uploaded successfully
	 * 400: Uploading avatar is not possible
	 */
	#[NoAdminRequired]
	#[OpenAPI(tags: ['user_avatar'])]
	public function postAvatar(): DataResponse {
		$files = $this->request->getUploadedFile('files');

		if (is_null($files)) {
			return new DataResponse(
				['message' => $this->l->t('No image file provided')],
				Http::STATUS_BAD_REQUEST
			);
		}

		if (
			$files['error'][0] === 0
			&& is_uploaded_file($files['tmp_name'][0])
			&& !Filesystem::isFileBlacklisted($files['tmp_name'][0])
		) {
			if ($files['size'][0] > 20 * 1024 * 1024) {
				return new DataResponse(
					['message' => $this->l->t('File is too big')],
					Http::STATUS_BAD_REQUEST
				);
			}
			$content = file_get_contents($files['tmp_name'][0]);
			// noopengrep: php.lang.security.unlink-use.unlink-use
			unlink($files['tmp_name'][0]);
		} else {
			return new DataResponse(
				['message' => $this->l->t('Invalid file provided')],
				Http::STATUS_BAD_REQUEST
			);
		}

		try {
			$image = new \OCP\Image();
			$image->loadFromData($content);
			$image->readExif($content);
			$image->fixOrientation();

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

			$avatar = $this->avatarManager->getAvatar($this->userId);
			$avatar->set($image);
			return new DataResponse(null);
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
	 * Delete your avatar as a user
	 *
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'avatar'}, array{}>
	 *
	 * 200: Avatar deleted successfully
	 * 400: Deleting avatar is not possible
	 */
	#[NoAdminRequired]
	#[OpenAPI(tags: ['user_avatar'])]
	public function deleteAvatar(): DataResponse {
		try {
			$avatar = $this->avatarManager->getAvatar($this->userId);
			$avatar->remove();
			return new DataResponse(null);
		} catch (\Exception $e) {
			$this->logger->error('Failed to delete avatar', [
				'exception' => $e,
			]);
			return new DataResponse(['error' => 'avatar'], Http::STATUS_BAD_REQUEST);
		}
	}
}

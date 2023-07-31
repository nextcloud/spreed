<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Kate Döen <kate.doeen@nextcloud.com>
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
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
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
	 * Upload a temporary avatar
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: Avatar uploaded successfully
	 * 400: Uploading avatar is not possible
	 */
	#[NoAdminRequired]
	public function postAvatar(): DataResponse {
		$files = $this->request->getUploadedFile('files');

		if (is_null($files)) {
			return new DataResponse(
				['message' => $this->l->t('No image file provided')],
				Http::STATUS_BAD_REQUEST
			);
		}

		if (
			$files['error'][0] === 0 &&
			is_uploaded_file($files['tmp_name'][0]) &&
			!Filesystem::isFileBlacklisted($files['tmp_name'][0])
		) {
			if ($files['size'][0] > 20 * 1024 * 1024) {
				return new DataResponse(
					['message' => $this->l->t('File is too big')],
					Http::STATUS_BAD_REQUEST
				);
			}
			$content = file_get_contents($files['tmp_name'][0]);
			unlink($files['tmp_name'][0]);
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
	 * Delete a temporary avatar
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST, array<empty>, array{}>
	 *
	 * 200: Avatar deleted successfully
	 * 400: Deleting avatar is not possible
	 */
	#[NoAdminRequired]
	public function deleteAvatar(): DataResponse {
		try {
			$avatar = $this->avatarManager->getAvatar($this->userId);
			$avatar->remove();
			return new DataResponse();
		} catch (\Exception $e) {
			$this->logger->error('Failed to delete avatar', [
				'exception' => $e,
			]);
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
	}
}

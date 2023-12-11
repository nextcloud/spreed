<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Share\Helper;

use OCP\FilesMetadata\Exceptions\FilesMetadataNotFoundException;
use OCP\FilesMetadata\Exceptions\FilesMetadataTypeException;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\FilesMetadata\Model\IFilesMetadata;

class FilesMetadataCache {
	/** @var array<int, ?array{width: int, height: int}> */
	protected array $filesSizeData = [];

	public function __construct(
		protected IFilesMetadataManager $filesMetadataManager,
	) {
	}

	/**
	 * @param list<int> $fileIds
	 */
	public function preloadMetadata(array $fileIds): void {
		$missingFileIds = array_diff($fileIds, array_keys($this->filesSizeData));
		if (empty($missingFileIds)) {
			return;
		}

		$data = $this->filesMetadataManager->getMetadataForFiles($missingFileIds);
		foreach ($data as $fileId => $metadata) {
			$this->cachePhotosSize($fileId, $metadata);
		}
	}

	/**
	 * @param int $fileId
	 * @return array
	 * @psalm-return array{width: int, height: int}
	 * @throws FilesMetadataNotFoundException
	 */
	public function getMetadataPhotosSizeForFileId(int $fileId): array {
		if (!array_key_exists($fileId, $this->filesSizeData)) {
			try {
				$this->cachePhotosSize($fileId, $this->filesMetadataManager->getMetadata($fileId, true));
			} catch (FilesMetadataNotFoundException) {
				$this->filesSizeData[$fileId] = null;
			}
		}

		$data = $this->filesSizeData[$fileId];
		if ($data === null) {
			throw new FilesMetadataNotFoundException();
		}

		return $data;
	}

	protected function cachePhotosSize(int $fileId, IFilesMetadata $metadata): void {
		if ($metadata->hasKey('photos-size')) {
			try {
				$sizeMetadata = $metadata->getArray('photos-size');
			} catch (FilesMetadataNotFoundException|FilesMetadataTypeException) {
				$this->filesSizeData[$fileId] = null;
				return;
			}

			if (isset($sizeMetadata['width'], $sizeMetadata['height'])) {
				$dimensions = [
					'width' => $sizeMetadata['width'],
					'height' => $sizeMetadata['height'],
				];
				$this->filesSizeData[$fileId] = $dimensions;
			} else {
				$this->filesSizeData[$fileId] = null;
			}
		} else {
			$this->filesSizeData[$fileId] = null;
		}

	}
}

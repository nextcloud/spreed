<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Share\Helper;

use OCP\FilesMetadata\Exceptions\FilesMetadataNotFoundException;
use OCP\FilesMetadata\Exceptions\FilesMetadataTypeException;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\FilesMetadata\Model\IFilesMetadata;

class FilesMetadataCache {
	/** @var array<int, ?array{width: int, height: int, blurhash?: string}> */
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
	 * @psalm-return array{width: int, height: int, blurhash?: string}
	 * @throws FilesMetadataNotFoundException
	 */
	public function getImageMetadataForFileId(int $fileId): array {
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

				// Retrieve Blurhash from metadata (if present)
				if ($metadata->hasKey('blurhash')) {
					$dimensions['blurhash'] = $metadata->getString('blurhash');
				}

				$this->filesSizeData[$fileId] = $dimensions;
			} else {
				$this->filesSizeData[$fileId] = null;
			}
		} else {
			$this->filesSizeData[$fileId] = null;
		}

	}
}

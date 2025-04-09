<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Share\Helper;

use OCA\Talk\Share\RoomShareProvider;
use OCP\Share\IShare;

/**
 * Instead of doing a single query to get each share and file metadata
 * we do a grouped query up front so the entries are cached
 */
class Preloader {
	public function __construct(
		protected RoomShareProvider $shareProvider,
		protected FilesMetadataCache $filesMetadataCache,
	) {
	}

	/*
	 * Gather share IDs from the comments and preload share definitions
	 * and files metadata to avoid separate database query for each
	 * individual share/node later on.
	 *
	 * @param IComment[] $comments
	 */
	public function preloadShares(array $comments): void {
		// Scan messages for share IDs
		$shareIds = [];
		foreach ($comments as $comment) {
			$verb = $comment->getVerb();
			if ($verb === 'object_shared') {
				$message = $comment->getMessage();
				$data = json_decode($message, true);
				if (isset($data['parameters']['share'])) {
					$shareIds[] = $data['parameters']['share'];
				}
			}
		}
		if (!empty($shareIds)) {
			// Retrieved Share objects will be cached by
			// the RoomShareProvider and returned from the cache to
			// the Parser\SystemMessage without additional database queries.
			$shares = $this->shareProvider->getSharesByIds($shareIds);

			// Preload files metadata as well
			$fileIds = array_filter(array_map(static fn (IShare $share) => $share->getNodeId(), $shares));
			$this->filesMetadataCache->preloadMetadata($fileIds);
		}
	}
}

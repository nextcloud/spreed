<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Config;
use OCA\Talk\ResponseDefinitions;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

/**
 * @psalm-import-type TalkSticker from ResponseDefinitions
 * @psalm-import-type TalkStickerPack from ResponseDefinitions
 */
class StickerService {
	/**
	 * Images clients can show inline. No SVG, as it can carry scripts.
	 */
	public const array MIME_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

	public function __construct(
		private readonly Config $talkConfig,
		private readonly IRootFolder $rootFolder,
	) {
	}

	/**
	 * The sticker packs the user can send: each subfolder of the stickers
	 * folder is a pack, images directly in the stickers folder form a pack of
	 * its own. Only images the user may share are listed, as sending a sticker
	 * shares its file with the conversation.
	 *
	 * @return list<TalkStickerPack>
	 */
	public function getPacks(string $userId): array {
		$path = $this->talkConfig->getStickersFolder();
		if ($path === '') {
			return [];
		}

		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			$root = $userFolder->get($path);
		} catch (NotFoundException|NotPermittedException) {
			return [];
		}
		if (!$root instanceof Folder) {
			return [];
		}

		$folders = [$root];
		$subfolders = array_filter($root->getDirectoryListing(), static fn (Node $node): bool => $node instanceof Folder);
		usort($subfolders, static fn (Node $a, Node $b): int => strnatcasecmp($a->getName(), $b->getName()));
		array_push($folders, ...$subfolders);

		$packs = [];
		foreach ($folders as $folder) {
			$stickers = $this->getStickers($userFolder, $folder);
			if ($stickers !== []) {
				$packs[] = [
					'id' => $folder->getId(),
					'name' => $folder->getName(),
					'stickers' => $stickers,
				];
			}
		}
		return $packs;
	}

	/**
	 * @return list<TalkSticker>
	 */
	protected function getStickers(Folder $userFolder, Folder $folder): array {
		$stickers = [];
		foreach ($folder->getDirectoryListing() as $node) {
			if (!$node instanceof File
				|| !in_array($node->getMimetype(), self::MIME_TYPES, true)
				|| !$node->isShareable()) {
				continue;
			}
			$stickers[] = [
				'id' => $node->getId(),
				'name' => pathinfo($node->getName(), PATHINFO_FILENAME),
				'path' => $userFolder->getRelativePath($node->getPath()) ?? '',
				'mimetype' => $node->getMimetype(),
				'etag' => $node->getEtag(),
			];
		}
		usort($stickers, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
		return $stickers;
	}
}

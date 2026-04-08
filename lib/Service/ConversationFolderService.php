<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Config as TalkConfig;
use OCA\Talk\Room;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotEnoughSpaceException;
use OCP\Files\NotFoundException;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Manages per-conversation attachment folders for Talk.
 *
 * Creates the three-level folder hierarchy on demand:
 *   <attachmentRoot>/<convFolderName>/<userSubfolderName>/
 *
 * and ensures a TYPE_ROOM share exists on the user subfolder so all
 * room members can access files uploaded there.
 */
class ConversationFolderService {
	public function __construct(
		private TalkConfig $talkConfig,
		private IRootFolder $rootFolder,
		private IShareManager $shareManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Returns the user's conversation subfolder for the given room,
	 * creating the full folder hierarchy and the share if not yet present.
	 *
	 * @throws NotEnoughSpaceException if the user's storage quota is exhausted
	 * @throws \RuntimeException if a path component exists but is not a folder
	 * @throws \OCP\Files\NotPermittedException if a folder cannot be created
	 */
	public function getOrCreateSubfolder(string $userId, Room $room): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);

		$freeSpace = $userFolder->getFreeSpace();
		if ($freeSpace === 0) {
			throw new NotEnoughSpaceException('User ' . $userId . ' has no free storage quota');
		}
		$attachmentFolder = ltrim($this->talkConfig->getAttachmentFolder($userId), '/');

		// Get or create attachment root (e.g. Talk/)
		try {
			$attachmentNode = $userFolder->get($attachmentFolder);
			if (!$attachmentNode instanceof Folder) {
				throw new \RuntimeException('Attachment folder path is not a directory: ' . $attachmentFolder);
			}
		} catch (NotFoundException) {
			$attachmentNode = $userFolder->newFolder($attachmentFolder);
		}

		// Get or create conversation folder (e.g. Talk/Room Name-token/).
		// Use getConversationFolderName() (based on getDisplayName()) so the name
		// matches what the client computes from conversation.displayName.
		$convFolderName = $this->talkConfig->getConversationFolderName($room, $userId);
		try {
			$convFolder = $attachmentNode->get($convFolderName);
			if (!$convFolder instanceof Folder) {
				throw new \RuntimeException('Conversation folder path is not a directory: ' . $convFolderName);
			}
		} catch (NotFoundException) {
			$convFolder = $attachmentNode->newFolder($convFolderName);
		}

		// Get or create user subfolder (e.g. Talk/Room Name-token/Alice-alice/)
		$subfolderName = $this->talkConfig->getConversationSubfolderName($userId);
		try {
			$subfolder = $convFolder->get($subfolderName);
			if (!$subfolder instanceof Folder) {
				throw new \RuntimeException('User subfolder path is not a directory: ' . $subfolderName);
			}
		} catch (NotFoundException) {
			$subfolder = $convFolder->newFolder($subfolderName);
		}

		$this->ensureSubfolderShared($subfolder, $userId, $room->getToken());

		return $subfolder;
	}

	/**
	 * Returns the draft upload folder for the given conversation subfolder.
	 *
	 * The Draft folder is a sibling of the user subfolder inside the conversation
	 * folder (e.g. Talk/Group Chat-abc123/Draft/).  It is NOT shared with the room,
	 * so room members cannot see files while they are being composed.  Files are
	 * moved into the shared user subfolder atomically when the message is posted.
	 *
	 * @throws \RuntimeException if the Draft path exists but is not a directory
	 */
	public function getOrCreateDraftFolder(Folder $subfolder): Folder {
		$convFolder = $subfolder->getParent();
		try {
			$draft = $convFolder->get('Draft');
			if (!$draft instanceof Folder) {
				throw new \RuntimeException('Draft path inside conversation folder is not a directory');
			}
			return $draft;
		} catch (NotFoundException) {
			return $convFolder->newFolder('Draft');
		}
	}

	/**
	 * Return the path of $folder relative to the user's home folder root
	 * (e.g. "Talk/Group Chat-abc123/alice-alice").
	 */
	public function getRelativePath(string $userId, Folder $folder): string {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		return ltrim($userFolder->getRelativePath($folder->getPath()), '/');
	}

	/**
	 * Look up a file node by path inside the given user's home folder.
	 *
	 * @throws NotFoundException if the path does not exist
	 */
	public function getFileNode(string $userId, string $filePath): Node {
		return $this->rootFolder->getUserFolder($userId)->get($filePath);
	}

	/**
	 * Ensure the uploaded file node is stored under $desiredName inside
	 * $subfolder, renaming to a unique variant (e.g. "photo (1).jpg") if
	 * $desiredName is already taken by a different file.
	 *
	 * Typical use: the client uploaded to a temp path; pass that node plus the
	 * original file name and this method moves/renames it in one step.
	 *
	 * @return array{from: string, to: string, node: Node}
	 */
	public function finalizeUploadedFile(Folder $subfolder, Node $node, string $desiredName): array {
		$finalName = $this->findUniqueName($subfolder, $desiredName, $node);
		$targetPath = $subfolder->getPath() . '/' . $finalName;
		$finalNode = $node;
		if ($node->getPath() !== $targetPath) {
			$finalNode = $node->move($targetPath);
		}
		return ['from' => $desiredName, 'to' => $finalName, 'node' => $finalNode];
	}

	/**
	 * Simulate rename-on-conflict for a batch of desired filenames without
	 * requiring the files to already exist.  Intra-batch name reservations are
	 * tracked so that two files with the same desired name in the same batch get
	 * distinct final names (e.g. "photo.jpg" and "photo (1).jpg").
	 *
	 * @param list<string> $desiredNames
	 * @return list<array<string, string>>
	 */
	public function probeFilenames(Folder $subfolder, array $desiredNames): array {
		$reserved = [];
		$result = [];
		foreach ($desiredNames as $desiredName) {
			$finalName = $this->findUniqueNameForProbe($subfolder, $desiredName, $reserved);
			$result[] = [$desiredName => $finalName];
			$reserved[] = $finalName;
		}
		return $result;
	}

	/**
	 * Find the first available name in $folder for $desiredName, treating
	 * $reserved (names claimed by earlier entries in the same probe batch) as
	 * already taken even if they do not yet exist on disk.
	 *
	 * @param list<string> $reserved
	 */
	private function findUniqueNameForProbe(Folder $folder, string $desiredName, array $reserved): string {
		if (!$this->isProbeNameTaken($folder, $desiredName, $reserved)) {
			return $desiredName;
		}

		$ext = pathinfo($desiredName, PATHINFO_EXTENSION);
		$base = $ext !== '' ? mb_substr($desiredName, 0, -(mb_strlen($ext) + 1)) : $desiredName;

		for ($i = 1; $i < 1000; $i++) {
			$candidate = $ext !== '' ? "$base ($i).$ext" : "$base ($i)";
			if (!$this->isProbeNameTaken($folder, $candidate, $reserved)) {
				return $candidate;
			}
		}

		do {
			$suffix = uniqid();
			$candidate = $ext !== '' ? "{$base}_{$suffix}.{$ext}" : "{$base}_{$suffix}";
		} while ($this->isProbeNameTaken($folder, $candidate, $reserved));
		return $candidate;
	}

	/**
	 * @param list<string> $reserved
	 */
	private function isProbeNameTaken(Folder $folder, string $name, array $reserved): bool {
		return in_array($name, $reserved, true) || $folder->nodeExists($name);
	}

	/**
	 * Find the first available name in $folder for $desiredName that does not
	 * conflict with any file other than $excludeNode itself.
	 * Tries "base (1).ext", "base (2).ext", … up to 999 before falling back
	 * to a uniqid suffix.
	 */
	private function findUniqueName(Folder $folder, string $desiredName, Node $excludeNode): string {
		try {
			$existing = $folder->get($desiredName);
			if ($existing->getId() === $excludeNode->getId()) {
				// File is already stored at the desired name — nothing to rename.
				return $desiredName;
			}
		} catch (NotFoundException) {
			return $desiredName;
		}

		$ext = pathinfo($desiredName, PATHINFO_EXTENSION);
		$base = $ext !== '' ? mb_substr($desiredName, 0, -(mb_strlen($ext) + 1)) : $desiredName;

		for ($i = 1; $i < 1000; $i++) {
			$candidate = $ext !== '' ? "$base ($i).$ext" : "$base ($i)";
			try {
				$folder->get($candidate);
			} catch (NotFoundException) {
				return $candidate;
			}
		}

		// Very unlikely: all 999 variants are taken — fall back to a unique suffix.
		do {
			$suffix = uniqid();
			$candidate = $ext !== '' ? "{$base}_{$suffix}.{$ext}" : "{$base}_{$suffix}";
			try {
				$existing = $folder->get($candidate);
				if ($existing->getId() === $excludeNode->getId()) {
					return $candidate;
				}
			} catch (NotFoundException) {
				return $candidate;
			}
		} while (true);
	}

	/**
	 * Ensure a TYPE_ROOM share exists on $folder for the given room token.
	 * Uses an optimistic create-and-catch approach so it works correctly even
	 * when the folder is already shared with many rooms (no limit on the check).
	 */
	private function ensureSubfolderShared(Folder $folder, string $userId, string $token): void {
		$share = $this->shareManager->newShare();
		$share->setNode($folder)
			->setShareType(IShare::TYPE_ROOM)
			->setSharedBy($userId)
			->setShareOwner($userId)
			->setSharedWith($token)
			->setPermissions(Constants::PERMISSION_READ)
			->setMailSend(false);

		try {
			$this->shareManager->createShare($share);
			$this->logger->debug('ConversationFolderService: created TYPE_ROOM share on {path} for room {token}', [
				'path' => $folder->getPath(),
				'token' => $token,
			]);
		} catch (GenericShareException $e) {
			if ($e->getMessage() !== 'Already shared') {
				throw $e;
			}
			// Share already exists — nothing to do.
		}
	}
}

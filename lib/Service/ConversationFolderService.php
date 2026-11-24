<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Config as TalkConfig;
use OCA\Talk\Room;
use OCA\Talk\Share\RoomShareProvider;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotEnoughSpaceException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\Teams\ITeamManager;
use Psr\Log\LoggerInterface;

/**
 * Manages per-conversation attachment folders for Talk.
 *
 * Creates the three-level folder hierarchy on demand:
 *   <attachmentRoot>/<convFolderName>/<userSubfolderName>/
 *
 * and ensures a TYPE_ROOM share exists on the user subfolder so all
 * room members can access files uploaded there.
 *
 * Team conversations use the folder of the team instead, when there is one
 * and the user can write to it:
 *   <teamFolder>/Talk/<userSubfolderName>/
 *
 * Everything the chat adds stays inside that one "Talk" folder, so the
 * structure of the team folder is not touched. No room share is created:
 * the participants are the team members, they all have the team folder
 * already, and a share would mount another "Talk" folder into every home.
 * Drafts and attachments of deleted messages stay in the home of the user,
 * other team members can not see them.
 */
class ConversationFolderService {
	public const UPDATABLE_SUFFIX = ' (u)';
	/** Folder inside the team folder that holds the files posted in the team conversation
	 * {TALK_PLACEHOLDER} cannot be used as there is no l10n that is available for a team */
	public const TEAM_ATTACHMENT_FOLDER = '.system';

	/** @var array<int, true> Ids of user subfolders created inside a team folder in this request */
	private array $teamSubfolderIds = [];

	public function __construct(
		private readonly TalkConfig $talkConfig,
		private readonly IRootFolder $rootFolder,
		private readonly IShareManager $shareManager,
		private readonly IFilenameValidator $filenameValidator,
		private readonly LoggerInterface $logger,
		private readonly ITeamManager $teamManager,
	) {
	}

	/**
	 * Ensure the user's base attachment folder (e.g. /Talk) exists,
	 * creating it if necessary.
	 */
	public function ensureAttachmentFolderExists(string $userId): void {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$attachmentFolder = ltrim($this->talkConfig->getAttachmentFolder($userId), '/');

		try {
			$node = $userFolder->get($attachmentFolder);
			if (!$node instanceof Folder) {
				throw new \RuntimeException('Attachment folder path is not a directory: ' . $attachmentFolder);
			}
		} catch (NotFoundException) {
			$userFolder->newFolder($attachmentFolder);
		}
	}

	/**
	 * Returns the user's conversation subfolder for the given room,
	 * creating the full folder hierarchy and the share if not yet present.
	 *
	 * @throws NotEnoughSpaceException if the user's storage quota is exhausted
	 * @throws \RuntimeException if a path component exists but is not a folder
	 * @throws \OCP\Files\NotPermittedException if a folder cannot be created
	 */
	public function getOrCreateSubfolder(string $userId, Room $room, bool $allowUpdate): Folder {
		$teamAttachmentFolder = $this->getTeamAttachmentFolder($userId, $room);
		if (!is_null($teamAttachmentFolder)) {
			if ($teamAttachmentFolder->getFreeSpace() === 0) {
				throw new NotEnoughSpaceException('Team folder of ' . $room->getObjectId() . ' has no free storage quota');
			}

			// Everyone in the team can edit the team folder anyway, so no " (u)" variant
			$subfolder = $this->getOrCreateChildFolder($teamAttachmentFolder, $this->talkConfig->getConversationSubfolderName($userId, false));
			$this->teamSubfolderIds[$subfolder->getId()] = true;
			return $subfolder;
		}

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
		$subfolderName = $this->talkConfig->getConversationSubfolderName($userId, $allowUpdate);
		try {
			$subfolder = $convFolder->get($subfolderName);
			if (!$subfolder instanceof Folder) {
				throw new \RuntimeException('User subfolder path is not a directory: ' . $subfolderName);
			}
		} catch (NotFoundException) {
			$subfolder = $convFolder->newFolder($subfolderName);
		}

		$this->ensureSubfolderShared($subfolder, $userId, $room->getToken(), $allowUpdate);

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
		return $this->getOrCreateSiblingFolder($subfolder, 'Draft');
	}

	/**
	 * Same as getOrCreateDraftFolder(), but for a subfolder in a team folder
	 * the Draft folder is in the conversation folder in the home of the user,
	 * so the team does not see files that are being composed.
	 *
	 * @throws \RuntimeException if a path component exists but is not a folder
	 */
	public function getOrCreateDraftFolderForUser(string $userId, Room $room, Folder $subfolder): Folder {
		if (!$this->isTeamSubfolder($subfolder)) {
			return $this->getOrCreateDraftFolder($subfolder);
		}
		return $this->getOrCreateChildFolder($this->getOrCreatePersonalConversationFolder($userId, $room), 'Draft');
	}

	/**
	 * Whether the subfolder returned by getOrCreateSubfolder() is in a team folder
	 */
	public function isTeamSubfolder(Folder $subfolder): bool {
		return isset($this->teamSubfolderIds[$subfolder->getId()]);
	}

	/**
	 * The "Talk" folder inside the team folder of a team conversation,
	 * null when the room is no team conversation, the team has no folder
	 * or the user can not write to it (then the home folder is used)
	 */
	public function getTeamAttachmentFolder(string $userId, Room $room): ?Folder {
		if (!$room->isTeamManaged()) {
			return null;
		}

		$folder = $this->getTeamFolder($userId, $room->getObjectId());
		if (!$folder instanceof Folder || !$folder->isCreatable()) {
			return null;
		}

		try {
			return $this->getOrCreateChildFolder($folder, self::TEAM_ATTACHMENT_FOLDER);
		} catch (\Throwable $e) {
			$this->logger->warning('Talk folder in the team folder of ' . $room->getToken() . ' could not be created', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * The folder of the team in the file tree of the user, null when the
	 * team has no folder or the user can not access it
	 */
	public function getTeamFolder(string $userId, string $teamId): ?Folder {
		try {
			$teamFolder = $this->teamManager->getTeamFolderProvider()?->getTeamFolder($teamId);
			if (is_null($teamFolder)) {
				return null;
			}
			$folder = $this->rootFolder->getUserFolder($userId)->get($teamFolder->getMountPoint());
		} catch (NotFoundException) {
			return null;
		} catch (\Throwable $e) {
			$this->logger->warning('Team folder of team ' . $teamId . ' could not be found', ['exception' => $e]);
			return null;
		}
		// The mount of the team folder, not a folder of the user with the same name
		return $folder instanceof Folder && empty($folder->getInternalPath()) ? $folder : null;
	}

	/**
	 * Move an attachment of a deleted message out of the team folder into
	 * the Deleted folder of the conversation in the home of the author, so
	 * only the author can still access it (like moveToDeletedFolder()).
	 *
	 * @throws NotPermittedException
	 */
	public function moveTeamAttachmentToDeletedFolder(string $ownerId, string $token, int $fileId): void {
		$node = $this->rootFolder->getUserFolder($ownerId)->getFirstNodeById($fileId);
		if (is_null($node) || $node->getType() !== FileInfo::TYPE_FILE) {
			// Deleted meanwhile, or the author has no access anymore (left the team)
			return;
		}
		if (!$this->isInTeamAttachmentFolderOf($node, $ownerId)) {
			// Moved meanwhile (e.g. filed elsewhere in the team folder): the team
			// works with it now, it is not taken away with the deleted message
			return;
		}

		$attachmentNode = $this->getOrCreateChildFolder(
			$this->rootFolder->getUserFolder($ownerId),
			mb_ltrim($this->talkConfig->getAttachmentFolder($ownerId), '/'),
		);

		// The conversation folder exists already from the Draft, its name has the token at the end.
		// Otherwise it is named after the team folder: <team folder>/.system/<user>/<file>
		$conversationFolder = null;
		foreach ($attachmentNode->getDirectoryListing() as $child) {
			if ($child instanceof Folder && str_ends_with($child->getName(), '-' . $token)) {
				$conversationFolder = $child;
				break;
			}
		}
		$conversationFolder ??= $attachmentNode->newFolder($this->talkConfig->buildConversationFolderName($node->getParent()->getParent()->getParent()->getName(), $token));

		$this->finalizeUploadedFile($this->getOrCreateChildFolder($conversationFolder, 'Deleted'), $node, $node->getName());
	}

	/**
	 * Whether the file is still where it was posted: <team folder>/.system/<name-uid>/<file>
	 */
	protected function isInTeamAttachmentFolderOf(Node $node, string $userId): bool {
		try {
			$userFolder = $node->getParent();
			$talkFolder = $userFolder->getParent();
			$teamFolder = $talkFolder->getParent();
		} catch (NotFoundException) {
			return false;
		}
		return str_ends_with($userFolder->getName(), '-' . $userId)
			&& $talkFolder->getName() === self::TEAM_ATTACHMENT_FOLDER
			&& empty($teamFolder->getInternalPath());
	}

	/**
	 * Talk/<Conversation>-<token>/ in the home of the user, without the shared user subfolder
	 *
	 * @throws \RuntimeException if a path component exists but is not a folder
	 */
	private function getOrCreatePersonalConversationFolder(string $userId, Room $room): Folder {
		$attachmentNode = $this->getOrCreateChildFolder(
			$this->rootFolder->getUserFolder($userId),
			mb_ltrim($this->talkConfig->getAttachmentFolder($userId), '/'),
		);
		return $this->getOrCreateChildFolder($attachmentNode, $this->talkConfig->getConversationFolderName($room, $userId));
	}

	/**
	 * @throws \RuntimeException if the path exists but is not a folder
	 */
	private function getOrCreateChildFolder(Folder $parent, string $name): Folder {
		try {
			$folder = $parent->get($name);
			if (!$folder instanceof Folder) {
				throw new \RuntimeException('Path is not a directory: ' . $name);
			}
			return $folder;
		} catch (NotFoundException) {
			return $parent->newFolder($name);
		}
	}

	/**
	 * Returns the folder for attachments of deleted messages, a non-shared
	 * sibling of the user subfolder (e.g. Talk/Group Chat-abc123/Deleted/).
	 *
	 * @throws \RuntimeException if the Deleted path exists but is not a directory
	 */
	public function getOrCreateDeletedFolder(Folder $subfolder): Folder {
		return $this->getOrCreateSiblingFolder($subfolder, 'Deleted');
	}

	private function getOrCreateSiblingFolder(Folder $subfolder, string $name): Folder {
		$convFolder = $subfolder->getParent();
		try {
			$folder = $convFolder->get($name);
			if (!$folder instanceof Folder) {
				throw new \RuntimeException($name . ' path inside conversation folder is not a directory');
			}
			return $folder;
		} catch (NotFoundException) {
			return $convFolder->newFolder($name);
		}
	}

	/**
	 * Move an attachment out of the room-shared user subfolder into the
	 * non-shared Deleted folder, so only the owner can still access it.
	 * Does nothing when the file is no longer inside a folder shared with the room.
	 *
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function moveToDeletedFolder(string $ownerId, string $token, int $fileId): void {
		$node = $this->rootFolder->getUserFolder($ownerId)->getFirstNodeById($fileId);
		if ($node === null || $node->getType() !== FileInfo::TYPE_FILE) {
			return;
		}

		$parent = $node->getParent();
		$shares = $this->shareManager->getSharesBy($ownerId, IShare::TYPE_ROOM, $parent, false, -1);
		foreach ($shares as $share) {
			if ($share->getSharedWith() === $token) {
				$this->finalizeUploadedFile($this->getOrCreateDeletedFolder($parent), $node, $node->getName());
				return;
			}
		}
	}

	/**
	 * Return the path of $folder relative to the user's home folder root
	 * (e.g. "Talk/Group Chat-abc123/alice-alice").
	 */
	public function getRelativePath(string $userId, Folder $folder): string {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		return ltrim((string)$userFolder->getRelativePath($folder->getPath()), '/');
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
	 * @throws NotEnoughSpaceException When the file does not fit into a team folder
	 * @throws NotPermittedException
	 */
	public function finalizeUploadedFile(Folder $subfolder, Node $node, string $desiredName): array {
		$isTeamSubfolder = $this->isTeamSubfolder($subfolder);
		if ($isTeamSubfolder) {
			// Draft is in the home of the user, the team folder is another storage
			// with its own quota: the move is a copy that the quota can refuse
			$freeSpace = $subfolder->getFreeSpace();
			if ($freeSpace >= 0 && $node->getSize() > $freeSpace) {
				throw new NotEnoughSpaceException('File exceeds the team folder quota');
			}
		}

		$finalName = $this->findUniqueName($subfolder, $desiredName, $node);
		$targetPath = $subfolder->getPath() . '/' . $finalName;
		$finalNode = $node;
		if ($node->getPath() !== $targetPath) {
			try {
				$finalNode = $node->move($targetPath);
			} catch (NotPermittedException $e) {
				if ($isTeamSubfolder) {
					// Free space changed meanwhile, or the folder reports none
					throw new NotEnoughSpaceException($e->getMessage(), previous: $e);
				}
				throw $e;
			}
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
		$desiredName = $this->filenameValidator->sanitizeFilename($desiredName);
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
	 * Share a file posted in a team conversation with the conversation, read
	 * only: participants without access to the team folder (federated users,
	 * email guests) open it with the public link of this share. Only the
	 * posted file, not the folder it is in (teammates can put other files
	 * there). Not mounted for local users (TEAM_FOLDER_TARGET), who have the
	 * file in the team folder.
	 *
	 * Created for every posted file, also without guests at the moment:
	 * guests invited later see everything posted before, like the members of
	 * the team. Nothing changes when the last guest leaves; whoever is removed
	 * from the conversation (removed from the team) loses access, as with
	 * every share of a conversation.
	 *
	 * Without permission to share in the team folder, only those participants
	 * can not open the file: posting works anyway.
	 */
	public function shareTeamAttachment(Node $node, string $userId, Room $room): void {
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_ROOM)
			->setSharedBy($userId)
			->setShareOwner($userId)
			->setSharedWith($room->getToken())
			->setPermissions(Constants::PERMISSION_READ)
			->setTarget(RoomShareProvider::TEAM_FOLDER_TARGET . '/' . $node->getName())
			->setMailSend(false);

		try {
			$this->shareManager->createShare($share);
		} catch (\Throwable $e) {
			if ($e instanceof GenericShareException && $e->getMessage() === 'Already shared') {
				return;
			}
			$this->logger->info('File of {user} in the team folder of {token} can not be shared, federated participants and email guests can not open it', [
				'user' => $userId,
				'token' => $room->getToken(),
				'exception' => $e,
			]);
		}
	}

	private function ensureSubfolderShared(Folder $folder, string $userId, string $token, bool $allowUpdate): void {
		$permissions = Constants::PERMISSION_READ;
		if ($allowUpdate) {
			$permissions |= Constants::PERMISSION_UPDATE;
		}

		$share = $this->shareManager->newShare();
		$share->setNode($folder)
			->setShareType(IShare::TYPE_ROOM)
			->setSharedBy($userId)
			->setShareOwner($userId)
			->setSharedWith($token)
			->setPermissions($permissions)
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

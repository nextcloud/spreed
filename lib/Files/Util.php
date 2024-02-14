<?php

declare(strict_types=1);
/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Talk\Files;

use OCA\Files_Sharing\SharedStorage;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;

class Util {
	/** @var array[] */
	private array $accessLists = [];
	/** @var bool[] */
	private array $publicAccessLists = [];

	public function __construct(
		private IRootFolder $rootFolder,
		private ISession $session,
		private IShareManager $shareManager,
		private IUserMountCache $userMountCache,
	) {
	}

	/**
	 * @return string[]
	 */
	public function getUsersWithAccessFile(string $fileId): array {
		if (!isset($this->accessLists[$fileId])) {
			$nodes = $this->rootFolder->getById((int) $fileId);

			if (empty($nodes)) {
				return [];
			}

			$node = array_shift($nodes);
			$accessList = $this->shareManager->getAccessList($node);
			if (!$node->getStorage()->instanceOfStorage(SharedStorage::class)) {
				// The file is not a shared file,
				// let's check the accesslist for mount points of groupfolders and external storages
				$mountsForFile = $this->userMountCache->getMountsForFileId($fileId);
				$affectedUserIds = array_map(function (ICachedMountInfo $mount) {
					return $mount->getUser()->getUID();
				}, $mountsForFile);

				$accessList['users'] = array_unique(array_merge($affectedUserIds, $accessList['users']));
			}

			$this->accessLists[$fileId] = $accessList['users'];
		}

		return $this->accessLists[$fileId];
	}

	public function canUserAccessFile(string $fileId, string $userId): bool {
		return \in_array($userId, $this->getUsersWithAccessFile($fileId), true);
	}

	public function canGuestsAccessFile(string $fileId): bool {
		if (!isset($this->publicAccessLists[$fileId])) {
			$nodes = $this->rootFolder->getById((int) $fileId);

			if (empty($nodes)) {
				return false;
			}

			$node = array_shift($nodes);
			$accessList = $this->shareManager->getAccessList($node, false);
			$this->publicAccessLists[$fileId] = $accessList['public'];
		}
		return $this->publicAccessLists[$fileId] === true;
	}

	public function canGuestAccessFile(string $shareToken): bool {
		try {
			$share = $this->shareManager->getShareByToken($shareToken);
			if ($share->getPassword() !== null) {
				$shareId = $this->session->get('public_link_authenticated');
				if ($share->getId() !== $shareId) {
					throw new ShareNotFound();
				}
			}
			return true;
		} catch (ShareNotFound $e) {
			return false;
		}
	}

	/**
	 * Returns any node of the file that is public and owned by the user, or
	 * that the user has direct access to.
	 *
	 * @param string $fileId
	 * @param string $userId
	 * @return Node|null
	 */
	public function getAnyNodeOfFileAccessibleByUser(string $fileId, string $userId): ?Node {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$nodes = $userFolder->getById((int) $fileId);

		$nodes = array_filter($nodes, static function (Node $node) {
			return $node->getType() === FileInfo::TYPE_FILE;
		});

		if (empty($nodes)) {
			return null;
		}

		return array_shift($nodes);
	}
}

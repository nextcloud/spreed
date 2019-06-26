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

namespace OCA\Spreed\Files;

use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;

class Util {

	/** @var IRootFolder */
	private $rootFolder;
	/** @var IShareManager */
	private $shareManager;
	/** @var array[] */
	private $accessLists = [];

	public function __construct(IRootFolder $rootFolder,
			IShareManager $shareManager) {
		$this->rootFolder = $rootFolder;
		$this->shareManager = $shareManager;
	}

	public function getUsersWithAccessFile(string $fileId): array {
		if (!isset($this->accessLists[$fileId])) {
			$nodes = $this->rootFolder->getById($fileId);

			if (empty($nodes)) {
				return [];
			}

			$node = array_shift($nodes);
			$accessList = $this->shareManager->getAccessList($node);

			$this->accessLists[$fileId] = $accessList['users'];
		}

		return $this->accessLists[$fileId];
	}

	public function canUserAccessFile(string $fileId, string $userId): bool {
		return \in_array($userId, $this->getUsersWithAccessFile($fileId), true);
	}

	/**
	 * Returns any share of the file that the user has direct access to.
	 *
	 * A user has direct access to a share and, thus, to a file, if she received
	 * the file through a user, group, circle or room share (but not through a
	 * public link, for example), or if she is the owner of such a share.
	 * Note that this includes too files received as a descendant of a folder
	 * that meets the above conditions.
	 *
	 * Only files are taken into account; folders are ignored.
	 *
	 * @param string $fileId
	 * @param string $userId
	 * @return IShare|null
	 */
	public function getAnyDirectShareOfFileAccessibleByUser(string $fileId, string $userId): ?IShare {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$nodes = $userFolder->getById($fileId);
		if (empty($nodes)) {
			return null;
		}

		$nodes = array_filter($nodes, function($node) {
			return $node->getType() === \OCP\Files\FileInfo::TYPE_FILE;
		});

		while (!empty($nodes)) {
			$node = array_pop($nodes);

			$share = $this->getAnyDirectShareOfNodeAccessibleByUser($node, $userId);
			if ($share) {
				return $share;
			}

			try {
				$nodes[] = $node->getParent();
			} catch (NotFoundException $e) {
			}
		}

		return null;
	}

	/**
	 * Returns any share of the node that the user has direct access to.
	 *
	 * @param Node $node
	 * @param string $userId
	 * @return IShare|null
	 */
	private function getAnyDirectShareOfNodeAccessibleByUser(Node $node, string $userId): ?IShare {
		$reshares = false;
		$limit = 1;

		$shares = $this->shareManager->getSharesBy($userId, \OCP\Share::SHARE_TYPE_USER, $node, $reshares, $limit);
		if (\count($shares) > 0) {
			return $shares[0];
		}

		$shares = $this->shareManager->getSharesBy($userId, \OCP\Share::SHARE_TYPE_GROUP, $node, $reshares, $limit);
		if (\count($shares) > 0) {
			return $shares[0];
		}

		$shares = $this->shareManager->getSharesBy($userId, \OCP\Share::SHARE_TYPE_CIRCLE, $node, $reshares, $limit);
		if (\count($shares) > 0) {
			return $shares[0];
		}

		$shares = $this->shareManager->getSharesBy($userId, \OCP\Share::SHARE_TYPE_ROOM, $node, $reshares, $limit);
		if (\count($shares) > 0) {
			return $shares[0];
		}

		// If the node is not shared then there is no need for further checks.
		// Note that "isShared()" returns false for owned shares, so the check
		// can not be moved above.
		if (!$node->isShared()) {
			return null;
		}

		$shares = $this->shareManager->getSharedWith($userId, \OCP\Share::SHARE_TYPE_USER, $node, $limit);
		if (\count($shares) > 0) {
			return $shares[0];
		}

		$shares = $this->shareManager->getSharedWith($userId, \OCP\Share::SHARE_TYPE_GROUP, $node, $limit);
		if (\count($shares) > 0) {
			return $shares[0];
		}

		$shares = $this->shareManager->getSharedWith($userId, \OCP\Share::SHARE_TYPE_CIRCLE, $node, $limit);
		if (\count($shares) > 0) {
			return $shares[0];
		}

		$shares = $this->shareManager->getSharedWith($userId, \OCP\Share::SHARE_TYPE_ROOM, $node, $limit);
		if (\count($shares) > 0) {
			return $shares[0];
		}

		return null;
	}

}

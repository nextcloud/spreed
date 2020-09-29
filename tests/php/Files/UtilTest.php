<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Tests\php\Files;

use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\Talk\Files\Util;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorage;
use OCP\ISession;
use OCP\Share\IManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class UtilTest extends TestCase {
	public function createNodeMock(string $type, bool $instanceOfGroupFolderStorage = false) {
		$node = $this->createMock(Node::class);
		$node->expects($this->once())
			->method('getType')
			->willReturn($type);

		if ($type === FileInfo::TYPE_FOLDER) {
			$node->expects($this->never())
				->method('getStorage');
		} else {
			$storage = $this->createMock(IStorage::class);
			$node->expects($this->any())
				->method('getStorage')
				->willReturn($storage);

			$storage->expects($this->any())
				->method('instanceOfStorage')
				->with(GroupFolderStorage::class)
				->willReturn($instanceOfGroupFolderStorage);
		}
		return $node;
	}

	public function dataGetGroupFolderNode(): array {
		return [
			['23', 'admin1', [], false],
			['24', 'admin2', [$this->createNodeMock(FileInfo::TYPE_FOLDER)], false],
			['25', 'admin3', [$this->createNodeMock(FileInfo::TYPE_FILE)], false],
			['26', 'admin4', [$this->createNodeMock(FileInfo::TYPE_FILE, true)], 0],
			['28', 'admin6', [
				$this->createNodeMock(FileInfo::TYPE_FOLDER),
				$this->createNodeMock(FileInfo::TYPE_FILE, true)
			], 1],
		];
	}

	/**
	 * @dataProvider dataGetGroupFolderNode
	 *
	 * @param string $fileId
	 * @param string $userId
	 * @param array $nodes
	 * @param bool|int $hasReturn
	 */
	public function testGetGroupFolderNode(string $fileId, string $userId, array $nodes, $return): void {
		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('getById')
			->with($fileId)
			->willReturn($nodes);

		/** @var IRootFolder|MockObject $rootFolder */
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->expects($this->once())
			->method('getUserFolder')
			->with($userId)
			->willReturn($userFolder);

		/** @var ISession|MockObject $session */
		$session = $this->createMock(ISession::class);

		/** @var IManager|MockObject $shareManager */
		$shareManager = $this->createMock(IManager::class);

		/** @var IUserMountCache|MockObject $userMountCache */
		$userMountCache = $this->createMock(IUserMountCache::class);

		$util = new Util(
			$rootFolder,
			$session,
			$shareManager,
			$userMountCache
		);
		$result = $util->getGroupFolderNode($fileId, $userId);
		if ($return !== false) {
			$this->assertSame($nodes[$return], $result);
		} else {
			$this->assertNull($result);
		}
	}
}

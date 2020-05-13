<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Daniel Rudolf <nextcloud.com@daniel-rudolf.de>
 *
 * @author Daniel Rudolf <nextcloud.com@daniel-rudolf.de>
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

namespace OCA\Talk\Tests\php\Command\Room;

use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;

trait TRoomCommandTest
{
	/** @var IUser[] */
	private $userMocks;

	/** @var IGroup[] */
	private $groupMocks;

	protected function registerUserManagerMock(): void {
		/** @var IUserManager|MockObject $userManager */
		$userManager = $this->createMock(IUserManager::class);

		$userManager->method('get')
			->willReturnCallback([$this, 'getUserMock']);

		$this->overwriteService(IUserManager::class, $userManager);
	}

	protected function createTestUserMocks(): void {
		$this->createUserMock('user1');
		$this->createUserMock('user2');
		$this->createUserMock('user3');
		$this->createUserMock('user4');
		$this->createUserMock('other');
	}

	public function getUserMock(string $uid): ?IUser {
		return $this->userMocks[$uid] ?? null;
	}

	protected function createUserMock(string $uid): IUser {
		/** @var IUser|MockObject $user */
		$user = $this->createMock(IUser::class);

		$this->userMocks[$uid] = $user;

		$user->method('getUID')
			->willReturn($uid);

		return $user;
	}

	protected function registerGroupManagerMock(): void {
		/** @var IGroupManager|MockObject $groupManager */
		$groupManager = $this->createMock(IGroupManager::class);

		$groupManager->method('get')
			->willReturnCallback([$this, 'getGroupMock']);

		$this->overwriteService(IGroupManager::class, $groupManager);
	}

	protected function createTestGroupMocks(): void {
		$this->createGroupMock('group1', ['user1', 'user2']);
		$this->createGroupMock('group2', ['user2', 'user3']);
		$this->createGroupMock('other', ['other']);
	}

	public function getGroupMock(string $gid): ?IGroup {
		return $this->groupMocks[$gid] ?? null;
	}

	protected function createGroupMock(string $gid, array $userIds): IGroup {
		/** @var IGroup|MockObject $group */
		$group = $this->createMock(IGroup::class);

		$this->groupMocks[$gid] = $group;

		$group->method('getGID')
			->willReturn($gid);

		$group->method('getUsers')
			->willReturnCallback(function () use ($userIds) {
				return array_map([$this, 'getUserMock'], $userIds);
			});

		return $group;
	}

	protected function createMock($originalClassName): MockObject {
		return $this->getMockBuilder($originalClassName)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->disableAutoReturnValueGeneration()
			->getMock();
	}
}

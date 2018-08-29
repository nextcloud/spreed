<?php
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Tests\php\Chat\Parser;

use OCA\Spreed\Chat\Parser\SystemMessage;
use OCA\Spreed\GuestManager;
use OCA\Spreed\Share\RoomShareProvider;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class SystemMessageTest extends TestCase {

	/** @var IUserManager|MockObject */
	protected $userManager;
	/** @var GuestManager|MockObject */
	protected $guestManager;
	/** @var IUserSession|MockObject */
	protected $userSession;
	/** @var RoomShareProvider|MockObject */
	protected $shareProvider;
	/** @var IRootFolder|MockObject */
	protected $rootFolder;
	/** @var IURLGenerator|MockObject */
	protected $url;
	/** @var IL10N|MockObject */
	protected $l;

	/** @var SystemMessage */
	protected $parser;

	public function setUp() {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->guestManager = $this->createMock(GuestManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->shareProvider = $this->createMock(RoomShareProvider::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->l = $this->createMock(IL10N::class);
	}

	protected function getParser(): SystemMessage {
		return new SystemMessage(
			$this->userManager,
			$this->guestManager,
			$this->userSession,
			$this->shareProvider,
			$this->rootFolder,
			$this->url,
			$this->l
		);
	}

	public function testSetUserInfoGuestToUser() {
		/** @var IUser $user */
		$user = $this->createMock(IUser::class);
		/** @var IL10N $l */
		$l = $this->createMock(IL10N::class);

		$parser = $this->getParser();
		$this->assertNull(self::invokePrivate($parser, 'recipient'));
		$this->assertSame($this->l, self::invokePrivate($parser, 'l'));
		$parser->setUserInfo($user, $l);
		$this->assertSame($user, self::invokePrivate($parser, 'recipient'));
		$this->assertSame($l, self::invokePrivate($parser, 'l'));
	}

	public function testSetUserInfoUser1ToUser2() {
		/** @var IUser $user1 */
		$user1 = $this->createMock(IUser::class);
		/** @var IUser $user2 */
		$user2 = $this->createMock(IUser::class);
		/** @var IL10N $l */
		$l = $this->createMock(IL10N::class);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user1);

		$parser = $this->getParser();
		$this->assertSame($user1, self::invokePrivate($parser, 'recipient'));
		$this->assertSame($this->l, self::invokePrivate($parser, 'l'));
		$parser->setUserInfo($user2, $l);
		$this->assertSame($user2, self::invokePrivate($parser, 'recipient'));
		$this->assertSame($l, self::invokePrivate($parser, 'l'));
	}

	public function testParseMessage() {

	}
}

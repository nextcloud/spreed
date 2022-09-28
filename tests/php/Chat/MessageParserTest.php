<?php
/**
 * @copyright Copyright (c) 2022, Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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

namespace OCA\Talk\Tests\php\Chat;

use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Share\RoomShareProvider;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserManager;
use Test\TestCase;

class MessageParserTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->roomShareProvider = $this->createMock(RoomShareProvider::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->messageParser = new MessageParser(
			$this->eventDispatcher,
			$this->roomShareProvider,
			$this->userManager
		);
	}


	/**
	 * @dataProvider dataIsSharedFile
	 */
	public function testIsSharedFile($message, $expected): void {
		$actual = $this->messageParser->isSharedFile($message);
		$this->assertEquals($expected, $actual);
	}

	public function dataIsSharedFile(): array {
		return [
			['', false],
			[json_encode([]), false],
			[json_encode(['parameters' => '']), false],
			[json_encode(['parameters' => []]), false],
			[json_encode(['parameters' => ['share' => null]]), false],
			[json_encode(['parameters' => ['share' => '']]), false],
			[json_encode(['parameters' => ['share' => []]]), false],
			[json_encode(['parameters' => ['share' => 0]]), false],
			[json_encode(['parameters' => ['share' => 1]]), true],
		];
	}
}

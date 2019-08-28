<?php
/**
 * @copyright Copyright (c) 2018 Peter Edens <petere@conceiva.com>
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
namespace OCA\Spreed\Tests\php;

use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCA\Spreed\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Test\TestCase;
use Symfony\Component\EventDispatcher\GenericEvent;

class PasswordVerificationTest extends TestCase {

	public function testVerifyPassword() {
		$dispatcher = new EventDispatcher();
		$dispatcher->addListener(Room::class . '::verifyPassword', function(GenericEvent $event) {
			$password = $event->getArgument('password');

			if ($password === '1234') {
				$event->setArgument('result',  [ 'result' => true, 'url' => '']);
			}
			else {
				$event->setArgument('result',  [ 'result' => false, 'url' => 'https://test']);
			}
		});

		$hasher = $this->createMock(IHasher::class);
		$room = new Room(
			$this->createMock(Manager::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(ISecureRandom::class),
			$dispatcher,
			$this->createMock(ITimeFactory::class),
			$hasher,
			1,
			Room::PUBLIC_CALL,
			Room::READ_WRITE,
			Webinary::LOBBY_NONE,
			'foobar',
			'Test',
			'passy',
			0
		);
		$verificationResult = $room->verifyPassword('1234');
		$this->assertSame($verificationResult, ['result' => true, 'url' => '']);
		$verificationResult = $room->verifyPassword('4321');
		$this->assertSame($verificationResult, ['result' => false, 'url' => 'https://test']);
		$this->assertSame('passy', $room->getPassword());
	}
}

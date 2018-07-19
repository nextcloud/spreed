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

use OCA\Spreed\Config;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use Test\TestCase;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * @group DB
 */
class PasswordVerificationTest extends TestCase {

	public function testVerifyPassword() {
		$dispatcher = \OC::$server->getEventDispatcher();

		$dispatcher->addListener('OCA\Spreed\Room::verifyPassword', function(GenericEvent $event) {
			$password = $event->getArgument('password');
			$room = $event->getSubject();
			$hasPassword = $room->hasPassword();

			if ($password == "1234") {
			    $event->setArgument('result',  [ 'result' => true, 'url' => '']);
			}
			else {
				$event->setArgument('result',  [ 'result' => false, 'url' => 'https://test']);
			}
        });

        $secureRandom = \OC::$server->getSecureRandom();
        $config = \OC::$server->getConfig();

        $dbConnection = \OC::$server->getDatabaseConnection();
        $dispatcher = \OC::$server->getEventDispatcher();
        $manager = new Manager($dbConnection, $config, $secureRandom, $dispatcher, $this->createMock(IHasher::class));
        $room = $manager->createPublicRoom();
        $verificationResult = $room->verifyPassword('1234');
        $this->assertSame($verificationResult, ['result' => true, 'url' => '']);
        $verificationResult = $room->verifyPassword('4321');
        $this->assertSame($verificationResult, ['result' => false, 'url' => 'https://test']);
	}
}

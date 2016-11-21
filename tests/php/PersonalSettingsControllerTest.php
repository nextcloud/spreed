<?php
/**
 * @author Joachim Bauch <mail@joachim-bauch.de>
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

use OCA\Spreed\Controller\PersonalSettingsController;
use OCA\Spreed\Util;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use Test\TestCase;

/**
 * Unittests for PersonalSettingsController.
 *
 * @group DB
 *
 */
class PersonalSettingsControllerTest extends TestCase {

    const TEST_PERSONAL_SETTINGS_USER = "test-personal-settings-user";

    /** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
    private $config;
    /** @var string */
    private $userId;
    /** @var PersonalSettingsController */
    private $controller;

    protected function setUp() {
        parent::setUp();

        $this->userId = self::TEST_PERSONAL_SETTINGS_USER;

        $this->config = \OC::$server->getConfig();
        $this->controller = new PersonalSettingsController(
            'spreed',
            $this->createMock(IRequest::class),
            $this->createMock(IL10N::class),
            $this->config,
            $this->userId
        );
    }

    protected function tearDown() {
        parent::tearDown();
    }

    public function testSetPersonalSettings() {
        $server = "server.domain.invalid:12345";
        $username = "foo";
        $password = "bar";
        $protocols = "udp,tcp";
        $response = $this->controller->setSpreedSettings($server, $username, $password, $protocols);
        $this->assertEquals('success', $response['status']);

        $settings = Util::getTurnSettings($this->config, $this->userId);
        $this->assertEquals($server, $settings['server']);
        $this->assertEquals($username, $settings['username']);
        $this->assertEquals($password, $settings['password']);
        $this->assertEquals($protocols, $settings['protocols']);
    }

}

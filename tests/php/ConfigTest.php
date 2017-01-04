<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
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
use Test\TestCase;

class ConfigTest extends TestCase {

	public function testGetStunServer() {
		/** @var \PHPUnit_Framework_MockObject_MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);
		/** @var \PHPUnit_Framework_MockObject_MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);
		$config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'stun_server', 'stun.nextcloud.com:443')
			->willReturn('88.198.160.129');

		$helper = new Config($config, $timeFactory);
		$this->assertSame('88.198.160.129', $helper->getStunServer());
	}

	public function testGenerateTurnSettings() {
		/** @var \PHPUnit_Framework_MockObject_MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);
		$config
			->expects($this->at(0))
			->method('getAppValue')
			->with('spreed', 'turn_server', '')
			->willReturn('turn.example.org');
		$config
			->expects($this->at(1))
			->method('getAppValue')
			->with('spreed', 'turn_server_secret', '')
			->willReturn('thisisasupersecretsecret');
		$config
			->expects($this->at(2))
			->method('getAppValue')
			->with('spreed', 'turn_server_protocols', '')
			->willReturn('udp,tcp');
		/** @var \PHPUnit_Framework_MockObject_MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(1479743025);

		$helper = new Config($config, $timeFactory);

		$this->assertSame(array(
			'server' => 'turn.example.org',
			'username' => '1479829425',
			'password' => 'ZY8fZQxAw/24gT0XYnMlcepUFlI=',
			'protocols' => 'udp,tcp',
		), $helper->getTurnSettings());
	}
}

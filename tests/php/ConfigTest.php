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
use OCP\Security\ISecureRandom;
use Test\TestCase;

class ConfigTest extends TestCase {

	public function testGetStunServer() {
		$servers = [
			'stun1.example.com:443',
			'stun2.example.com:129',
		];

		/** @var \PHPUnit_Framework_MockObject_MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);
		/** @var \PHPUnit_Framework_MockObject_MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);
		/** @var \PHPUnit_Framework_MockObject_MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);
		$config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'stun_servers', json_encode(['stun.nextcloud.com:443']))
			->willReturn(json_encode($servers));

		$helper = new Config($config, $secureRandom, $timeFactory);
		$this->assertTrue(in_array($helper->getStunServer(), $servers, true));
	}

	public function testGenerateTurnSettings() {

		/** @var \PHPUnit_Framework_MockObject_MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);
		$config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'turn_servers', '')
			->willReturn(json_encode([
				[
					'server' => 'turn.example.org',
					'secret' => 'thisisasupersecretsecret',
					'protocols' => 'udp,tcp',
				],
				[
					'server' => 'turn2.example.com',
					'secret' => 'ThisIsAlsoSuperSecret',
					'protocols' => 'tcp',
				],
			]));

		/** @var \PHPUnit_Framework_MockObject_MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(1479743025);

		/** @var \PHPUnit_Framework_MockObject_MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);
		$helper = new Config($config, $secureRandom, $timeFactory);

		//
		$server = $helper->getTurnSettings();
		if ($server['server'] === 'turn.example.org') {
			$this->assertSame([
				'server' => 'turn.example.org',
				'username' => '1479829425',
				'password' => 'ZY8fZQxAw/24gT0XYnMlcepUFlI=',
				'protocols' => 'udp,tcp',
			], $server);
		} else {
			$this->assertSame([
				'server' => 'turn2.example.com',
				'username' => '1479829425',
				'password' => 'VoqRpE4ktQ85TqFps8Qt+scEEvE=',
				'protocols' => 'tcp',
			], $server);
		}
	}
}

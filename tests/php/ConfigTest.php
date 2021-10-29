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
namespace OCA\Talk\Tests\php;

use OCA\Talk\Config;
use OCA\Talk\Events\GetTurnServersEvent;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ConfigTest extends TestCase {
	public function testGetStunServers() {
		$servers = [
			'stun1.example.com:443',
			'stun2.example.com:129',
		];

		/** @var MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);
		/** @var MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);
		/** @var MockObject|IGroupManager $secureRandom */
		$groupManager = $this->createMock(IGroupManager::class);
		/** @var MockObject|IEventDispatcher $dispatcher */
		$dispatcher = $this->createMock(IEventDispatcher::class);
		/** @var MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);
		$config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'stun_servers', json_encode(['stun.nextcloud.com:443']))
			->willReturn(json_encode($servers));
		$config
			->expects($this->once())
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(true);

		$helper = new Config($config, $secureRandom, $groupManager, $timeFactory, $dispatcher);
		$this->assertSame($helper->getStunServers(), $servers);
	}

	public function testGetDefaultStunServer() {
		/** @var MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);
		/** @var MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);
		/** @var MockObject|IGroupManager $secureRandom */
		$groupManager = $this->createMock(IGroupManager::class);
		/** @var MockObject|IEventDispatcher $dispatcher */
		$dispatcher = $this->createMock(IEventDispatcher::class);
		/** @var MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);
		$config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'stun_servers', json_encode(['stun.nextcloud.com:443']))
			->willReturn(json_encode([]));
		$config
			->expects($this->once())
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(true);

		$helper = new Config($config, $secureRandom, $groupManager, $timeFactory, $dispatcher);
		$this->assertSame(['stun.nextcloud.com:443'], $helper->getStunServers());
	}

	public function testGetDefaultStunServerNoInternet() {
		/** @var MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);
		/** @var MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);
		/** @var MockObject|IGroupManager $secureRandom */
		$groupManager = $this->createMock(IGroupManager::class);
		/** @var MockObject|IEventDispatcher $dispatcher */
		$dispatcher = $this->createMock(IEventDispatcher::class);
		/** @var MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);
		$config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'stun_servers', json_encode(['stun.nextcloud.com:443']))
			->willReturn(json_encode([]));
		$config
			->expects($this->once())
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(false);

		$helper = new Config($config, $secureRandom, $groupManager, $timeFactory, $dispatcher);
		$this->assertSame([], $helper->getStunServers());
	}

	public function testGenerateTurnSettings() {

		/** @var MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);
		$config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'turn_servers', '')
			->willReturn(json_encode([
				[
					// No scheme explicitly given
					'server' => 'turn.example.org:3478',
					'secret' => 'thisisasupersecretsecret',
					'protocols' => 'udp,tcp',
				],
				[
					'schemes' => 'turn,turns',
					'server' => 'turn2.example.com:5349',
					'secret' => 'ThisIsAlsoSuperSecret',
					'protocols' => 'udp',
				],
				[
					'schemes' => 'turns',
					'server' => 'turn-tls.example.com:443',
					'secret' => 'ThisIsAlsoSuperSecret',
					'protocols' => 'tcp',
				],
			]));

		/** @var MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(1479743025);

		/** @var MockObject|IGroupManager $secureRandom */
		$groupManager = $this->createMock(IGroupManager::class);
		/** @var MockObject|IEventDispatcher $dispatcher */
		$dispatcher = $this->createMock(IEventDispatcher::class);

		/** @var MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);
		$secureRandom
			->expects($this->once())
			->method('generate')
			->with(16)
			->willReturn('abcdefghijklmnop');
		$helper = new Config($config, $secureRandom, $groupManager, $timeFactory, $dispatcher);

		//
		$settings = $helper->getTurnSettings();
		$this->assertEquals(3, count($settings));
		$this->assertSame([
			'schemes' => 'turn',
			'server' => 'turn.example.org:3478',
			'username' => '1479829425:abcdefghijklmnop',
			'password' => '4VJLVbihLzuxgMfDrm5C3zy8kLQ=',
			'protocols' => 'udp,tcp',
		], $settings[0]);
		$this->assertSame([
			'schemes' => 'turn,turns',
			'server' => 'turn2.example.com:5349',
			'username' => '1479829425:abcdefghijklmnop',
			'password' => 'Ol9DEqnvyN4g+IAM+vFnqhfWUTE=',
			'protocols' => 'udp',
		], $settings[1]);
		$this->assertSame([
			'schemes' => 'turns',
			'server' => 'turn-tls.example.com:443',
			'username' => '1479829425:abcdefghijklmnop',
			'password' => 'Ol9DEqnvyN4g+IAM+vFnqhfWUTE=',
			'protocols' => 'tcp',
		], $settings[2]);
	}

	public function testGenerateTurnSettingsEmpty() {
		/** @var MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);
		$config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'turn_servers', '')
			->willReturn(json_encode([]));

		/** @var MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);

		/** @var MockObject|IGroupManager $secureRandom */
		$groupManager = $this->createMock(IGroupManager::class);

		/** @var MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);

		/** @var MockObject|IEventDispatcher $dispatcher */
		$dispatcher = $this->createMock(IEventDispatcher::class);

		$helper = new Config($config, $secureRandom, $groupManager, $timeFactory, $dispatcher);

		$settings = $helper->getTurnSettings();
		$this->assertEquals(0, count($settings));
	}

	public function testGenerateTurnSettingsEvent() {
		/** @var MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);
		$config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'turn_servers', '')
			->willReturn(json_encode([]));

		/** @var MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);

		/** @var MockObject|IGroupManager $secureRandom */
		$groupManager = $this->createMock(IGroupManager::class);

		/** @var MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);

		/** @var IEventDispatcher $dispatcher */
		$dispatcher = \OC::$server->query(IEventDispatcher::class);

		$servers = [
			[
				'schemes' => 'turn',
				'server' => 'turn.domain.invalid',
				'username' => 'john',
				'password' => 'abcde',
				'protocols' => 'udp,tcp',
			],
			[
				'schemes' => 'turns',
				'server' => 'turns.domain.invalid',
				'username' => 'jane',
				'password' => 'ABCDE',
				'protocols' => 'tcp',
			],
		];

		$listener = static function (GetTurnServersEvent $event) use ($servers) {
			$event->setServers($servers);
		};

		$dispatcher->addListener(Config::EVENT_GET_TURN_SERVERS, $listener);

		$helper = new Config($config, $secureRandom, $groupManager, $timeFactory, $dispatcher);

		$settings = $helper->getTurnSettings();
		$this->assertSame($servers, $settings);
	}

	public function dataGetWebSocketDomainForSignalingServer() {
		return [
			['http://blabla.nextcloud.com', 'ws://blabla.nextcloud.com'],
			['http://blabla.nextcloud.com/', 'ws://blabla.nextcloud.com'],
			['http://blabla.nextcloud.com/signaling', 'ws://blabla.nextcloud.com'],
			['http://blabla.nextcloud.com/signaling/', 'ws://blabla.nextcloud.com'],
			['http://blabla.nextcloud.com:80', 'ws://blabla.nextcloud.com:80'],
			['http://blabla.nextcloud.com:80/', 'ws://blabla.nextcloud.com:80'],
			['http://blabla.nextcloud.com:80/signaling', 'ws://blabla.nextcloud.com:80'],
			['http://blabla.nextcloud.com:80/signaling/', 'ws://blabla.nextcloud.com:80'],
			['http://blabla.nextcloud.com:8000', 'ws://blabla.nextcloud.com:8000'],
			['http://blabla.nextcloud.com:8000/', 'ws://blabla.nextcloud.com:8000'],
			['http://blabla.nextcloud.com:8000/signaling', 'ws://blabla.nextcloud.com:8000'],
			['http://blabla.nextcloud.com:8000/signaling/', 'ws://blabla.nextcloud.com:8000'],

			['https://blabla.nextcloud.com', 'wss://blabla.nextcloud.com'],
			['https://blabla.nextcloud.com/', 'wss://blabla.nextcloud.com'],
			['https://blabla.nextcloud.com/signaling', 'wss://blabla.nextcloud.com'],
			['https://blabla.nextcloud.com/signaling/', 'wss://blabla.nextcloud.com'],
			['https://blabla.nextcloud.com:443', 'wss://blabla.nextcloud.com:443'],
			['https://blabla.nextcloud.com:443/', 'wss://blabla.nextcloud.com:443'],
			['https://blabla.nextcloud.com:443/signaling', 'wss://blabla.nextcloud.com:443'],
			['https://blabla.nextcloud.com:443/signaling/', 'wss://blabla.nextcloud.com:443'],
			['https://blabla.nextcloud.com:8443', 'wss://blabla.nextcloud.com:8443'],
			['https://blabla.nextcloud.com:8443/', 'wss://blabla.nextcloud.com:8443'],
			['https://blabla.nextcloud.com:8443/signaling', 'wss://blabla.nextcloud.com:8443'],
			['https://blabla.nextcloud.com:8443/signaling/', 'wss://blabla.nextcloud.com:8443'],

			['ws://blabla.nextcloud.com', 'ws://blabla.nextcloud.com'],
			['ws://blabla.nextcloud.com/', 'ws://blabla.nextcloud.com'],
			['ws://blabla.nextcloud.com/signaling', 'ws://blabla.nextcloud.com'],
			['ws://blabla.nextcloud.com/signaling/', 'ws://blabla.nextcloud.com'],
			['ws://blabla.nextcloud.com:80', 'ws://blabla.nextcloud.com:80'],
			['ws://blabla.nextcloud.com:80/', 'ws://blabla.nextcloud.com:80'],
			['ws://blabla.nextcloud.com:80/signaling', 'ws://blabla.nextcloud.com:80'],
			['ws://blabla.nextcloud.com:80/signaling/', 'ws://blabla.nextcloud.com:80'],
			['ws://blabla.nextcloud.com:8000', 'ws://blabla.nextcloud.com:8000'],
			['ws://blabla.nextcloud.com:8000/', 'ws://blabla.nextcloud.com:8000'],
			['ws://blabla.nextcloud.com:8000/signaling', 'ws://blabla.nextcloud.com:8000'],
			['ws://blabla.nextcloud.com:8000/signaling/', 'ws://blabla.nextcloud.com:8000'],

			['wss://blabla.nextcloud.com', 'wss://blabla.nextcloud.com'],
			['wss://blabla.nextcloud.com/', 'wss://blabla.nextcloud.com'],
			['wss://blabla.nextcloud.com/signaling', 'wss://blabla.nextcloud.com'],
			['wss://blabla.nextcloud.com/signaling/', 'wss://blabla.nextcloud.com'],
			['wss://blabla.nextcloud.com:443', 'wss://blabla.nextcloud.com:443'],
			['wss://blabla.nextcloud.com:443/', 'wss://blabla.nextcloud.com:443'],
			['wss://blabla.nextcloud.com:443/signaling', 'wss://blabla.nextcloud.com:443'],
			['wss://blabla.nextcloud.com:443/signaling/', 'wss://blabla.nextcloud.com:443'],
			['wss://blabla.nextcloud.com:8443', 'wss://blabla.nextcloud.com:8443'],
			['wss://blabla.nextcloud.com:8443/', 'wss://blabla.nextcloud.com:8443'],
			['wss://blabla.nextcloud.com:8443/signaling', 'wss://blabla.nextcloud.com:8443'],
			['wss://blabla.nextcloud.com:8443/signaling/', 'wss://blabla.nextcloud.com:8443'],
		];
	}

	/**
	 * @dataProvider dataGetWebSocketDomainForSignalingServer
	 * @param string $url
	 * @param string $expectedWebSocketDomain
	 */
	public function testGetWebSocketDomainForSignalingServer($url, $expectedWebSocketDomain) {
		/** @var MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);
		/** @var MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);
		/** @var MockObject|IGroupManager $secureRandom */
		$groupManager = $this->createMock(IGroupManager::class);
		/** @var MockObject|IEventDispatcher $dispatcher */
		$dispatcher = $this->createMock(IEventDispatcher::class);
		/** @var MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);

		$helper = new Config($config, $secureRandom, $groupManager, $timeFactory, $dispatcher);

		$this->assertEquals(
			$expectedWebSocketDomain,
			self::invokePrivate($helper, 'getWebSocketDomainForSignalingServer', [$url])
		);
	}
}

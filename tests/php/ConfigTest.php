<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Tests\php;

use OCA\Talk\Config;
use OCA\Talk\Events\BeforeTurnServersGetEvent;
use OCA\Talk\Tests\php\Mocks\GetTurnServerListener;
use OCA\Talk\Vendor\Firebase\JWT\JWT;
use OCA\Talk\Vendor\Firebase\JWT\Key;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ConfigTest extends TestCase {
	private function createConfig(IConfig $config) {
		/** @var MockObject|IAppConfig $appConfig */
		$appConfig = $this->createMock(IAppConfig::class);
		/** @var MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);
		/** @var MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);
		/** @var MockObject|IGroupManager $groupManager */
		$groupManager = $this->createMock(IGroupManager::class);
		/** @var MockObject|IUserManager $userManager */
		$userManager = $this->createMock(IUserManager::class);
		/** @var MockObject|IURLGenerator $urlGenerator */
		$urlGenerator = $this->createMock(IURLGenerator::class);
		/** @var MockObject|IEventDispatcher $dispatcher */
		$dispatcher = $this->createMock(IEventDispatcher::class);

		$helper = new Config($config, $appConfig, $secureRandom, $groupManager, $userManager, $urlGenerator, $timeFactory, $dispatcher);
		return $helper;
	}

	public function testGetStunServers(): void {
		$servers = [
			'stun1.example.com:443',
			'stun2.example.com:129',
		];

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

		$helper = $this->createConfig($config);
		$this->assertSame($helper->getStunServers(), $servers);
	}

	public function testGetDefaultStunServer(): void {
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

		$helper = $this->createConfig($config);
		$this->assertSame(['stun.nextcloud.com:443'], $helper->getStunServers());
	}

	public function testGetDefaultStunServerNoInternet(): void {
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

		$helper = $this->createConfig($config);
		$this->assertSame([], $helper->getStunServers());
	}

	public function testGenerateTurnSettings(): void {
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

		/** @var MockObject|IAppConfig $appConfig */
		$appConfig = $this->createMock(IAppConfig::class);
		/** @var MockObject|IGroupManager $groupManager */
		$groupManager = $this->createMock(IGroupManager::class);
		/** @var MockObject|IUserManager $userManager */
		$userManager = $this->createMock(IUserManager::class);
		/** @var MockObject|IURLGenerator $urlGenerator */
		$urlGenerator = $this->createMock(IURLGenerator::class);
		/** @var MockObject|IEventDispatcher $dispatcher */
		$dispatcher = $this->createMock(IEventDispatcher::class);

		/** @var MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);
		$secureRandom
			->expects($this->once())
			->method('generate')
			->with(16)
			->willReturn('abcdefghijklmnop');
		$helper = new Config($config, $appConfig, $secureRandom, $groupManager, $userManager, $urlGenerator, $timeFactory, $dispatcher);

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

	public function testGenerateTurnSettingsEmpty(): void {
		/** @var MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);
		$config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'turn_servers', '')
			->willReturn(json_encode([]));

		$helper = $this->createConfig($config);

		$settings = $helper->getTurnSettings();
		$this->assertEquals(0, count($settings));
	}

	public function testGenerateTurnSettingsEvent(): void {
		/** @var MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);
		$config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'turn_servers', '')
			->willReturn(json_encode([]));

		/** @var MockObject|IAppConfig $appConfig */
		$appConfig = $this->createMock(IAppConfig::class);

		/** @var MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);

		/** @var MockObject|IGroupManager $groupManager */
		$groupManager = $this->createMock(IGroupManager::class);

		/** @var MockObject|IUserManager $userManager */
		$userManager = $this->createMock(IUserManager::class);

		/** @var MockObject|IURLGenerator $urlGenerator */
		$urlGenerator = $this->createMock(IURLGenerator::class);

		/** @var MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);

		/** @var IEventDispatcher $dispatcher */
		$dispatcher = \OCP\Server::get(IEventDispatcher::class);

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

		$dispatcher->addServiceListener(BeforeTurnServersGetEvent::class, GetTurnServerListener::class);

		$helper = new Config($config, $appConfig, $secureRandom, $groupManager, $userManager, $urlGenerator, $timeFactory, $dispatcher);

		$settings = $helper->getTurnSettings();
		$this->assertSame($servers, $settings);
	}

	public static function dataGetWebSocketDomainForSignalingServer(): array {
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

			// Admin got interrupted before finishing typing
			['wss://', ''],
			['ws://', ''],
			['https://', ''],
			['http://', ''],
			['wss:/', ''],
			['https:/', ''],
			['wss:', ''],
			['https:', ''],
			['wss', 'wss'],
			['https', 'https'],
			['ws', 'ws'],
			['http', 'http'],
			['w', 'w'],
			['htt', 'htt'],
			['ht', 'ht'],
			['h', 'h'],
		];
	}

	/**
	 * @param string $url
	 * @param string $expectedWebSocketDomain
	 */
	#[DataProvider('dataGetWebSocketDomainForSignalingServer')]
	public function testGetWebSocketDomainForSignalingServer($url, $expectedWebSocketDomain): void {
		/** @var MockObject|IConfig $config */
		$config = $this->createMock(IConfig::class);

		$helper = $this->createConfig($config);

		$this->assertEquals(
			$expectedWebSocketDomain,
			self::invokePrivate($helper, 'getWebSocketDomainForSignalingServer', [$url])
		);
	}

	public static function dataTicketV2Algorithm(): array {
		return [
			['ES384'],
			['ES256'],
			['RS256'],
			['RS384'],
			['RS512'],
			['EdDSA'],
		];
	}

	#[DataProvider('dataTicketV2Algorithm')]
	public function testSignalingTicketV2User(string $algo): void {
		$config = \OCP\Server::get(IConfig::class);
		/** @var MockObject|IAppConfig $appConfig */
		$appConfig = $this->createMock(IAppConfig::class);
		/** @var MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);
		/** @var MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);
		/** @var MockObject|IGroupManager $groupManager */
		$groupManager = $this->createMock(IGroupManager::class);
		/** @var MockObject|IUserManager $userManager */
		$userManager = $this->createMock(IUserManager::class);
		/** @var MockObject|IURLGenerator $urlGenerator */
		$urlGenerator = $this->createMock(IURLGenerator::class);
		/** @var MockObject|IEventDispatcher $dispatcher */
		$dispatcher = $this->createMock(IEventDispatcher::class);
		/** @var MockObject|IUser $user */
		$user = $this->createMock(IUser::class);

		$now = time();
		$timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn($now);
		$urlGenerator
			->expects($this->once())
			->method('getAbsoluteURL')
			->with('')
			->willReturn('https://domain.invalid/nextcloud');
		$userManager
			->expects($this->once())
			->method('get')
			->with('user1')
			->willReturn($user);
		$user
			->expects($this->once())
			->method('getUID')
			->willReturn('user1');
		$user
			->expects($this->once())
			->method('getDisplayName')
			->willReturn('Jane Doe');

		$helper = new Config($config, $appConfig, $secureRandom, $groupManager, $userManager, $urlGenerator, $timeFactory, $dispatcher);

		$config->setAppValue('spreed', 'signaling_token_alg', $algo);
		// Make sure new keys are generated.
		$config->deleteAppValue('spreed', 'signaling_token_privkey_' . strtolower($algo));
		$config->deleteAppValue('spreed', 'signaling_token_pubkey_' . strtolower($algo));
		$ticket = $helper->getSignalingTicket(Config::SIGNALING_TICKET_V2, 'user1');
		$this->assertNotNull($ticket);

		$key = new Key($config->getAppValue('spreed', 'signaling_token_pubkey_' . strtolower($algo)), $algo);
		$decoded = JWT::decode($ticket, $key);

		$this->assertEquals($now, $decoded->iat);
		$this->assertEquals('https://domain.invalid/nextcloud', $decoded->iss);
		$this->assertEquals('user1', $decoded->sub);
		$this->assertSame(['displayname' => 'Jane Doe'], (array)$decoded->userdata);
	}

	#[DataProvider('dataTicketV2Algorithm')]
	public function testSignalingTicketV2Anonymous(string $algo): void {
		/** @var IConfig $config */
		$config = \OCP\Server::get(IConfig::class);
		/** @var MockObject|IAppConfig $appConfig */
		$appConfig = $this->createMock(IAppConfig::class);
		/** @var MockObject|ITimeFactory $timeFactory */
		$timeFactory = $this->createMock(ITimeFactory::class);
		/** @var MockObject|ISecureRandom $secureRandom */
		$secureRandom = $this->createMock(ISecureRandom::class);
		/** @var MockObject|IGroupManager $groupManager */
		$groupManager = $this->createMock(IGroupManager::class);
		/** @var MockObject|IUserManager $userManager */
		$userManager = $this->createMock(IUserManager::class);
		/** @var MockObject|IURLGenerator $urlGenerator */
		$urlGenerator = $this->createMock(IURLGenerator::class);
		/** @var MockObject|IEventDispatcher $dispatcher */
		$dispatcher = $this->createMock(IEventDispatcher::class);

		$now = time();
		$timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn($now);
		$urlGenerator
			->expects($this->once())
			->method('getAbsoluteURL')
			->with('')
			->willReturn('https://domain.invalid/nextcloud');

		$helper = new Config($config, $appConfig, $secureRandom, $groupManager, $userManager, $urlGenerator, $timeFactory, $dispatcher);

		$config->setAppValue('spreed', 'signaling_token_alg', $algo);
		// Make sure new keys are generated.
		$config->deleteAppValue('spreed', 'signaling_token_privkey_' . strtolower($algo));
		$config->deleteAppValue('spreed', 'signaling_token_pubkey_' . strtolower($algo));
		$ticket = $helper->getSignalingTicket(Config::SIGNALING_TICKET_V2, null);
		$this->assertNotNull($ticket);

		$key = new Key($config->getAppValue('spreed', 'signaling_token_pubkey_' . strtolower($algo)), $algo);
		$decoded = JWT::decode($ticket, $key);

		$this->assertEquals($now, $decoded->iat);
		$this->assertEquals('https://domain.invalid/nextcloud', $decoded->iss);
	}
}

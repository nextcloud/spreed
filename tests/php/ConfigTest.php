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
use OCP\Config\IUserConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IFilenameValidator;
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
	protected IConfig $config;
	protected IAppConfig&MockObject $appConfig;
	protected IUserConfig&MockObject $userConfig;
	protected ISecureRandom&MockObject $secureRandom;
	protected IGroupManager&MockObject $groupManager;
	protected IUserManager&MockObject $userManager;
	protected IURLGenerator&MockObject $urlGenerator;
	protected ITimeFactory&MockObject $timeFactory;
	protected IEventDispatcher $dispatcher;
	protected IFilenameValidator&MockObject $filenameValidator;

	public function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->userConfig = $this->createMock(IUserConfig::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->filenameValidator = $this->createMock(IFilenameValidator::class);
	}

	protected function getConfig(): Config {
		return new Config(
			$this->config,
			$this->appConfig,
			$this->userConfig,
			$this->secureRandom,
			$this->groupManager,
			$this->userManager,
			$this->urlGenerator,
			$this->timeFactory,
			$this->dispatcher,
			$this->filenameValidator,
		);
	}

	public function testGetStunServers(): void {
		$servers = [
			'stun1.example.com:443',
			'stun2.example.com:129',
		];

		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'stun_servers', json_encode(['stun.nextcloud.com:443']))
			->willReturn(json_encode($servers));
		$this->config
			->expects($this->once())
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(true);

		$helper = $this->getConfig();
		$this->assertSame($helper->getStunServers(), $servers);
	}

	public function testGetDefaultStunServer(): void {
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'stun_servers', json_encode(['stun.nextcloud.com:443']))
			->willReturn(json_encode([]));
		$this->config
			->expects($this->once())
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(true);

		$helper = $this->getConfig();
		$this->assertSame(['stun.nextcloud.com:443'], $helper->getStunServers());
	}

	public function testGetDefaultStunServerNoInternet(): void {
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'stun_servers', json_encode(['stun.nextcloud.com:443']))
			->willReturn(json_encode([]));
		$this->config
			->expects($this->once())
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(false);

		$helper = $this->getConfig();
		$this->assertSame([], $helper->getStunServers());
	}

	public function testGenerateTurnSettings(): void {
		$this->config
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

		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(1479743025);

		$this->secureRandom
			->expects($this->once())
			->method('generate')
			->with(16)
			->willReturn('abcdefghijklmnop');

		$helper = $this->getConfig();

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
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'turn_servers', '')
			->willReturn(json_encode([]));

		$helper = $this->getConfig();

		$settings = $helper->getTurnSettings();
		$this->assertEquals(0, count($settings));
	}

	public function testGenerateTurnSettingsEvent(): void {
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'turn_servers', '')
			->willReturn(json_encode([]));

		$this->dispatcher = \OCP\Server::get(IEventDispatcher::class);

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

		$this->dispatcher->addServiceListener(BeforeTurnServersGetEvent::class, GetTurnServerListener::class);

		$helper = $this->getConfig();

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
		$helper = $this->getConfig();

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
		$this->config = \OCP\Server::get(IConfig::class);

		/** @var IUser&MockObject $user */
		$user = $this->createMock(IUser::class);

		$now = time();
		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn($now);
		$this->urlGenerator
			->expects($this->once())
			->method('getAbsoluteURL')
			->with('')
			->willReturn('https://domain.invalid/nextcloud');
		$this->userManager
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

		$helper = $this->getConfig();

		$this->config->setAppValue('spreed', 'signaling_token_alg', $algo);
		// Make sure new keys are generated.
		$this->config->deleteAppValue('spreed', 'signaling_token_privkey_' . strtolower($algo));
		$this->config->deleteAppValue('spreed', 'signaling_token_pubkey_' . strtolower($algo));
		$ticket = $helper->getSignalingTicket(Config::SIGNALING_TICKET_V2, 'user1');
		$this->assertNotNull($ticket);

		$key = new Key($this->config->getAppValue('spreed', 'signaling_token_pubkey_' . strtolower($algo)), $algo);
		$decoded = JWT::decode($ticket, $key);

		$this->assertEquals($now, $decoded->iat);
		$this->assertEquals('https://domain.invalid/nextcloud', $decoded->iss);
		$this->assertEquals('user1', $decoded->sub);
		$this->assertSame(['displayname' => 'Jane Doe'], (array)$decoded->userdata);
	}

	#[DataProvider('dataTicketV2Algorithm')]
	public function testSignalingTicketV2Anonymous(string $algo): void {
		$this->config = \OCP\Server::get(IConfig::class);

		$now = time();
		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn($now);
		$this->urlGenerator
			->expects($this->once())
			->method('getAbsoluteURL')
			->with('')
			->willReturn('https://domain.invalid/nextcloud');

		$helper = $this->getConfig();

		$this->config->setAppValue('spreed', 'signaling_token_alg', $algo);
		// Make sure new keys are generated.
		$this->config->deleteAppValue('spreed', 'signaling_token_privkey_' . strtolower($algo));
		$this->config->deleteAppValue('spreed', 'signaling_token_pubkey_' . strtolower($algo));
		$ticket = $helper->getSignalingTicket(Config::SIGNALING_TICKET_V2, null);
		$this->assertNotNull($ticket);

		$key = new Key($this->config->getAppValue('spreed', 'signaling_token_pubkey_' . strtolower($algo)), $algo);
		$decoded = JWT::decode($ticket, $key);

		$this->assertEquals($now, $decoded->iat);
		$this->assertEquals('https://domain.invalid/nextcloud', $decoded->iss);
	}

}

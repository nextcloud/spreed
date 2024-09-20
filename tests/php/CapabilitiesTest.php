<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\Unit;

use OCA\Talk\Capabilities;
use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Config;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Capabilities\IPublicCapability;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Translation\ITranslationManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CapabilitiesTest extends TestCase {
	protected IConfig&MockObject $serverConfig;
	protected Config&MockObject $talkConfig;
	protected IAppConfig&MockObject $appConfig;
	protected CommentsManager&MockObject $commentsManager;
	protected IUserSession&MockObject $userSession;
	protected IAppManager&MockObject $appManager;
	protected ITranslationManager&MockObject $translationManager;
	protected ICacheFactory&MockObject $cacheFactory;
	protected ICache&MockObject $talkCache;

	public function setUp(): void {
		parent::setUp();
		$this->serverConfig = $this->createMock(IConfig::class);
		$this->talkConfig = $this->createMock(Config::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->commentsManager = $this->createMock(CommentsManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->translationManager = $this->createMock(ITranslationManager::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->talkCache = $this->createMock(ICache::class);

		$this->cacheFactory->method('createLocal')
			->with('talk::')
			->willReturn($this->talkCache);

		$this->commentsManager->expects($this->any())
			->method('supportReactions')
			->willReturn(true);

		$this->appManager->expects($this->any())
			->method('getAppVersion')
			->with('spreed')
			->willReturn('1.2.3');
	}

	public function testGetCapabilitiesGuest(): void {
		$capabilities = new Capabilities(
			$this->serverConfig,
			$this->talkConfig,
			$this->appConfig,
			$this->commentsManager,
			$this->userSession,
			$this->appManager,
			$this->translationManager,
			$this->cacheFactory,
		);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn(null);

		$this->talkConfig->expects($this->never())
			->method('isDisabledForUser');

		$this->talkConfig->expects($this->once())
			->method('isBreakoutRoomsEnabled')
			->willReturn(false);

		$this->serverConfig->expects($this->any())
			->method('getAppValue')
			->willReturnMap([
				['spreed', 'has_reference_id', 'no', 'no'],
				['spreed', 'max-gif-size', '3145728', '200000'],
				['spreed', 'start_calls', (string)Room::START_CALL_EVERYONE, (string)Room::START_CALL_EVERYONE],
				['spreed', 'session-ping-limit', '200', '200'],
				['core', 'backgroundjobs_mode', 'ajax', 'cron'],
			]);

		$this->assertInstanceOf(IPublicCapability::class, $capabilities);
		$this->assertSame([
			'spreed' => [
				'features' => array_merge(
					Capabilities::FEATURES, [
						'message-expiration',
						'reactions',
					]
				),
				'features-local' => Capabilities::LOCAL_FEATURES,
				'config' => [
					'attachments' => [
						'allowed' => false,
					],
					'call' => [
						'enabled' => true,
						'breakout-rooms' => false,
						'recording' => false,
						'recording-consent' => 0,
						'supported-reactions' => ['❤️', '🎉', '👏', '👋', '👍', '👎', '🔥', '😂', '🤩', '🤔', '😲', '😥'],
						'can-upload-background' => false,
						'sip-enabled' => false,
						'sip-dialout-enabled' => false,
						'can-enable-sip' => false,
						'predefined-backgrounds' => [
							'1_office.jpg',
							'2_home.jpg',
							'3_abstract.jpg',
							'4_beach.jpg',
							'5_park.jpg',
							'6_theater.jpg',
							'7_library.jpg',
							'8_space_station.jpg',
						],
					],
					'chat' => [
						'max-length' => 32000,
						'read-privacy' => 0,
						'has-translation-providers' => false,
						'typing-privacy' => 0,
					],
					'conversations' => [
						'can-create' => false,
					],
					'federation' => [
						'enabled' => false,
						'incoming-enabled' => false,
						'outgoing-enabled' => false,
						'only-trusted-servers' => true,
					],
					'previews' => [
						'max-gif-size' => 200000,
					],
					'signaling' => [
						'session-ping-limit' => 200,
					],
				],
				'config-local' => Capabilities::LOCAL_CONFIGS,
				'version' => '1.2.3',
			],
		], $capabilities->getCapabilities());
	}

	public static function dataGetCapabilitiesUserAllowed(): array {
		return [
			[true, false, 'none', true, Participant::PRIVACY_PRIVATE],
			[false, true, '1 MB', true, Participant::PRIVACY_PUBLIC],
			[false, true, '0 B', false, Participant::PRIVACY_PUBLIC],
		];
	}

	/**
	 * @dataProvider dataGetCapabilitiesUserAllowed
	 */
	public function testGetCapabilitiesUserAllowed(bool $isNotAllowed, bool $canCreate, string $quota, bool $canUpload, int $readPrivacy): void {
		$capabilities = new Capabilities(
			$this->serverConfig,
			$this->talkConfig,
			$this->appConfig,
			$this->commentsManager,
			$this->userSession,
			$this->appManager,
			$this->translationManager,
			$this->cacheFactory,
		);

		$user = $this->createMock(IUser::class);
		$user->expects($this->atLeastOnce())
			->method('getUID')
			->willReturn('uid');
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->talkConfig->expects($this->once())
			->method('isDisabledForUser')
			->with($user)
			->willReturn(false);

		$this->talkConfig->expects($this->once())
			->method('isBreakoutRoomsEnabled')
			->willReturn(true);

		$this->talkConfig->expects($this->once())
			->method('getAttachmentFolder')
			->with('uid')
			->willReturn('/Talk');

		$this->talkConfig->expects($this->once())
			->method('isNotAllowedToCreateConversations')
			->with($user)
			->willReturn($isNotAllowed);

		$this->talkConfig->expects($this->once())
			->method('getUserReadPrivacy')
			->with('uid')
			->willReturn($readPrivacy);

		$user->method('getQuota')
			->willReturn($quota);

		$this->serverConfig->expects($this->any())
			->method('getAppValue')
			->willReturnMap([
				['spreed', 'has_reference_id', 'no', 'yes'],
				['spreed', 'max-gif-size', '3145728', '200000'],
				['spreed', 'start_calls', (string)Room::START_CALL_EVERYONE, (string)Room::START_CALL_NOONE],
				['spreed', 'session-ping-limit', '200', '50'],
				['core', 'backgroundjobs_mode', 'ajax', 'cron'],
			]);

		$this->assertInstanceOf(IPublicCapability::class, $capabilities);
		$data = $capabilities->getCapabilities();
		$this->assertSame([
			'spreed' => [
				'features' => array_merge(
					Capabilities::FEATURES, [
						'message-expiration',
						'reactions',
					]
				),
				'features-local' => Capabilities::LOCAL_FEATURES,
				'config' => [
					'attachments' => [
						'allowed' => true,
						'folder' => '/Talk',
					],
					'call' => [
						'enabled' => false,
						'breakout-rooms' => true,
						'recording' => false,
						'recording-consent' => 0,
						'supported-reactions' => ['❤️', '🎉', '👏', '👋', '👍', '👎', '🔥', '😂', '🤩', '🤔', '😲', '😥'],
						'can-upload-background' => $canUpload,
						'sip-enabled' => false,
						'sip-dialout-enabled' => false,
						'can-enable-sip' => false,
						'predefined-backgrounds' => [
							'1_office.jpg',
							'2_home.jpg',
							'3_abstract.jpg',
							'4_beach.jpg',
							'5_park.jpg',
							'6_theater.jpg',
							'7_library.jpg',
							'8_space_station.jpg',
						],
					],
					'chat' => [
						'max-length' => 32000,
						'read-privacy' => $readPrivacy,
						'has-translation-providers' => false,
						'typing-privacy' => 0,
					],
					'conversations' => [
						'can-create' => $canCreate,
					],
					'federation' => [
						'enabled' => false,
						'incoming-enabled' => false,
						'outgoing-enabled' => false,
						'only-trusted-servers' => true,
					],
					'previews' => [
						'max-gif-size' => 200000,
					],
					'signaling' => [
						'session-ping-limit' => 50,
					],
				],
				'config-local' => Capabilities::LOCAL_CONFIGS,
				'version' => '1.2.3',
			],
		], $data);

		foreach ($data['spreed']['features'] as $feature) {
			$suffix = '';
			if (in_array($feature, $data['spreed']['features-local'])) {
				$suffix = ' (local)';
			}
			$this->assertCapabilityIsDocumented("`$feature`" . $suffix);
		}

		foreach ($data['spreed']['config'] as $feature => $configs) {
			foreach ($configs as $config => $configData) {
				$suffix = '';
				if (isset($data['spreed']['config-local'][$feature]) && in_array($config, $data['spreed']['config-local'][$feature])) {
					$suffix = ' (local)';
				}
				$this->assertCapabilityIsDocumented("`config => $feature => $config`" . $suffix);
			}
		}
	}

	protected function assertCapabilityIsDocumented(string $capability): void {
		$docs = file_get_contents(__DIR__ . '/../../docs/capabilities.md');
		self::assertStringContainsString($capability, $docs, 'Asserting that capability ' . $capability . ' is documented');
	}

	public function testGetCapabilitiesUserDisallowed(): void {
		$capabilities = new Capabilities(
			$this->serverConfig,
			$this->talkConfig,
			$this->appConfig,
			$this->commentsManager,
			$this->userSession,
			$this->appManager,
			$this->translationManager,
			$this->cacheFactory,
		);

		$user = $this->createMock(IUser::class);
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->talkConfig->expects($this->once())
			->method('isDisabledForUser')
			->with($user)
			->willReturn(true);

		$this->assertInstanceOf(IPublicCapability::class, $capabilities);
		$this->assertSame([], $capabilities->getCapabilities());
	}

	public function testCapabilitiesHelloV2Key(): void {
		$capabilities = new Capabilities(
			$this->serverConfig,
			$this->talkConfig,
			$this->appConfig,
			$this->commentsManager,
			$this->userSession,
			$this->appManager,
			$this->translationManager,
			$this->cacheFactory,
		);

		$this->talkConfig->expects($this->once())
			->method('getSignalingTokenPublicKey')
			->willReturn('this-is-the-key');

		$data = $capabilities->getCapabilities();
		$this->assertEquals('this-is-the-key', $data['spreed']['config']['signaling']['hello-v2-token-key']);
	}

	public static function dataTestConfigRecording(): array {
		return [
			[true],
			[false],
		];
	}

	/**
	 * @dataProvider dataTestConfigRecording
	 */
	public function testConfigRecording(bool $enabled): void {
		$capabilities = new Capabilities(
			$this->serverConfig,
			$this->talkConfig,
			$this->appConfig,
			$this->commentsManager,
			$this->userSession,
			$this->appManager,
			$this->translationManager,
			$this->cacheFactory,
		);

		$this->talkConfig->expects($this->once())
			->method('isRecordingEnabled')
			->willReturn($enabled);

		$data = $capabilities->getCapabilities();
		$this->assertEquals($data['spreed']['config']['call']['recording'], $enabled);
	}

	public function testCapabilitiesTranslations(): void {
		$capabilities = new Capabilities(
			$this->serverConfig,
			$this->talkConfig,
			$this->appConfig,
			$this->commentsManager,
			$this->userSession,
			$this->appManager,
			$this->translationManager,
			$this->cacheFactory,
		);

		$this->translationManager->method('hasProviders')
			->willReturn(true);

		$data = json_decode(json_encode($capabilities->getCapabilities(), JSON_THROW_ON_ERROR), true);
		$this->assertEquals(true, $data['spreed']['config']['chat']['has-translation-providers']);
	}
}

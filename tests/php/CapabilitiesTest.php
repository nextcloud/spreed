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
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\TaskTypes\TextToTextFormalization;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;
use OCP\TaskProcessing\TaskTypes\TextToTextTranslate;
use OCP\Translation\ITranslationManager;
use PHPUnit\Framework\Attributes\DataProvider;
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
	protected ITaskProcessingManager&MockObject $taskProcessingManager;
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
		$this->taskProcessingManager = $this->createMock(ITaskProcessingManager::class);
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

	protected function getCapabilities(): Capabilities {
		return new Capabilities(
			$this->serverConfig,
			$this->talkConfig,
			$this->appConfig,
			$this->commentsManager,
			$this->userSession,
			$this->appManager,
			$this->translationManager,
			$this->taskProcessingManager,
			$this->cacheFactory,
		);
	}

	public function testGetCapabilitiesGuest(): void {
		$capabilities = $this->getCapabilities();

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn(null);

		$this->talkConfig->expects($this->never())
			->method('isDisabledForUser');

		$this->talkConfig->method('getConversationsListStyle')
			->willReturn('two-lines');

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

		$this->appConfig->method('getAppValueInt')
			->willReturnMap([
				['max_call_duration', 0, 0],
				['retention_event_rooms', 28, 28],
				['retention_phone_rooms', 7, 7],
				['retention_instant_meetings', 1, 1],
				['experiments_guests', 0, 0],
				['summary_threshold', 100, 100],
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
						'start-without-media' => false,
						'max-duration' => 0,
						'blur-virtual-background' => false,
						'end-to-end-encryption' => false,
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
						'predefined-backgrounds-v2' => [
							'/img/backgrounds/1_office.jpg',
							'/img/backgrounds/2_home.jpg',
							'/img/backgrounds/3_abstract.jpg',
							'/img/backgrounds/4_beach.jpg',
							'/img/backgrounds/5_park.jpg',
							'/img/backgrounds/6_theater.jpg',
							'/img/backgrounds/7_library.jpg',
							'/img/backgrounds/8_space_station.jpg',
						],
					],
					'chat' => [
						'max-length' => 32000,
						'read-privacy' => 0,
						'has-translation-providers' => false,
						'has-translation-task-providers' => false,
						'typing-privacy' => 0,
						'summary-threshold' => 100,
					],
					'conversations' => [
						'can-create' => false,
						'force-passwords' => false,
						'list-style' => 'two-lines',
						'description-length' => 2000,
						'retention-event' => 28,
						'retention-phone' => 7,
						'retention-instant-meetings' => 1,
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
					'experiments' => [
						'enabled' => 0,
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

	#[DataProvider('dataGetCapabilitiesUserAllowed')]
	public function testGetCapabilitiesUserAllowed(bool $isNotAllowed, bool $canCreate, string $quota, bool $canUpload, int $readPrivacy): void {
		$capabilities = $this->getCapabilities();

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

		$this->talkConfig->method('getConversationsListStyle')
			->willReturn('two-lines');

		$user->method('getQuota')
			->willReturn($quota);

		$this->taskProcessingManager->method('getAvailableTaskTypes')
			->willReturn([TextToTextSummary::ID => true]);

		$this->serverConfig->expects($this->any())
			->method('getAppValue')
			->willReturnMap([
				['spreed', 'has_reference_id', 'no', 'yes'],
				['spreed', 'max-gif-size', '3145728', '200000'],
				['spreed', 'start_calls', (string)Room::START_CALL_EVERYONE, (string)Room::START_CALL_NOONE],
				['spreed', 'session-ping-limit', '200', '50'],
				['core', 'backgroundjobs_mode', 'ajax', 'cron'],
			]);

		$this->appConfig->expects($this->any())
			->method('getAppValueBool')
			->willReturnMap([
				['backgrounds_default_for_users', true, true],
				['backgrounds_upload_users', true, true],
			]);

		$this->appConfig->method('getAppValueInt')
			->willReturnMap([
				['max_call_duration', 0, 0],
				['retention_event_rooms', 28, 28],
				['retention_phone_rooms', 7, 7],
				['retention_instant_meetings', 1, 1],
				['experiments_users', 0, 0],
				['summary_threshold', 100, 100],
			]);

		$this->assertInstanceOf(IPublicCapability::class, $capabilities);
		$data = $capabilities->getCapabilities();
		$this->assertSame([
			'spreed' => [
				'features' => array_merge(
					Capabilities::FEATURES, [
						'message-expiration',
						'reactions',
						'chat-summary-api',
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
						'start-without-media' => false,
						'max-duration' => 0,
						'blur-virtual-background' => false,
						'end-to-end-encryption' => false,
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
						'predefined-backgrounds-v2' => [
							'/img/backgrounds/1_office.jpg',
							'/img/backgrounds/2_home.jpg',
							'/img/backgrounds/3_abstract.jpg',
							'/img/backgrounds/4_beach.jpg',
							'/img/backgrounds/5_park.jpg',
							'/img/backgrounds/6_theater.jpg',
							'/img/backgrounds/7_library.jpg',
							'/img/backgrounds/8_space_station.jpg',
						],
					],
					'chat' => [
						'max-length' => 32000,
						'read-privacy' => $readPrivacy,
						'has-translation-providers' => false,
						'has-translation-task-providers' => false,
						'typing-privacy' => 0,
						'summary-threshold' => 100,
					],
					'conversations' => [
						'can-create' => $canCreate,
						'force-passwords' => false,
						'list-style' => 'two-lines',
						'description-length' => 2000,
						'retention-event' => 28,
						'retention-phone' => 7,
						'retention-instant-meetings' => 1,
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
					'experiments' => [
						'enabled' => 0,
					],
				],
				'config-local' => Capabilities::LOCAL_CONFIGS,
				'version' => '1.2.3',
			],
		], $data);
	}

	public function testCapabilitiesDocumentation(): void {
		foreach (Capabilities::FEATURES as $feature) {
			$suffix = ' - ';
			if (in_array($feature, Capabilities::LOCAL_FEATURES)) {
				$suffix = ' (local) - ';
			}
			$this->assertCapabilityIsDocumented("`$feature`" . $suffix);
		}

		foreach (Capabilities::CONDITIONAL_FEATURES as $feature) {
			$suffix = ' - ';
			if (in_array($feature, Capabilities::LOCAL_FEATURES)) {
				$suffix = ' (local) - ';
			}
			$this->assertCapabilityIsDocumented("`$feature`" . $suffix);
		}

		$openapi = json_decode(file_get_contents(__DIR__ . '/../../openapi.json'), true, flags: JSON_THROW_ON_ERROR);
		$configDefinition = $openapi['components']['schemas']['Capabilities']['properties']['config']['properties'] ?? null;
		$this->assertIsArray($configDefinition, 'Failed to read Capabilities config from openapi.json');

		$configFeatures = array_keys($configDefinition);

		foreach ($configFeatures as $feature) {
			foreach (array_keys($configDefinition[$feature]['properties']) as $config) {
				$suffix = '';
				if (in_array($config, Capabilities::LOCAL_CONFIGS[$feature])) {
					$suffix = ' (local)';
				}
				$this->assertCapabilityIsDocumented("`config => $feature => $config`" . $suffix . ' - ');
			}
		}
	}

	protected function assertCapabilityIsDocumented(string $capability): void {
		$docs = file_get_contents(__DIR__ . '/../../docs/capabilities.md');
		self::assertStringContainsString($capability, $docs, 'Asserting that capability ' . $capability . ' is documented');
	}

	public function testGetCapabilitiesUserDisallowed(): void {
		$capabilities = $this->getCapabilities();

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
		$capabilities = $this->getCapabilities();

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

	#[DataProvider('dataTestConfigRecording')]
	public function testConfigRecording(bool $enabled): void {
		$capabilities = $this->getCapabilities();

		$this->talkConfig->expects($this->once())
			->method('isRecordingEnabled')
			->willReturn($enabled);

		$data = $capabilities->getCapabilities();
		$this->assertEquals($data['spreed']['config']['call']['recording'], $enabled);
	}

	public function testCapabilitiesTranslations(): void {
		$capabilities = $this->getCapabilities();

		$this->translationManager->method('hasProviders')
			->willReturn(true);

		$data = json_decode(json_encode($capabilities->getCapabilities(), JSON_THROW_ON_ERROR), true);
		$this->assertEquals(true, $data['spreed']['config']['chat']['has-translation-providers']);
	}

	public function testCapabilitiesTranslationsTaskProviders(): void {
		$capabilities = $this->getCapabilities();

		$this->taskProcessingManager->method('getAvailableTaskTypes')
			->willReturn([TextToTextTranslate::ID => true]);

		$data = json_decode(json_encode($capabilities->getCapabilities(), JSON_THROW_ON_ERROR), true);
		$this->assertEquals(true, $data['spreed']['config']['chat']['has-translation-task-providers']);
	}

	public function testSummaryTaskProviders(): void {
		$capabilities = $this->getCapabilities();

		$this->taskProcessingManager->method('getAvailableTaskTypes')
			->willReturn([TextToTextFormalization::ID => true]);

		$data = json_decode(json_encode($capabilities->getCapabilities(), JSON_THROW_ON_ERROR), true);
		$this->assertNotContains('chat-summary-api', $data['spreed']['features']);
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\AppAPI\PublicFunctions;
use OCA\AppAPI\Service\ExAppService;
use OCA\Talk\Config;
use OCA\Talk\Service\LiveTranscriptionService;
use OCA\Talk\Service\RoomService;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Http\Client\IResponse;
use OCP\L10N\IFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class LiveTranscriptionServiceTest extends TestCase {
	protected IAppConfig&MockObject $appConfig;
	protected IAppManager&MockObject $appManager;
	protected LoggerInterface&MockObject $logger;
	protected LiveTranscriptionService $service;

	/**
	 * Stand-in for the AppAPI classes used by the service. AppAPI is not
	 * necessarily available when running the tests, so its classes can not be
	 * mocked directly.
	 */
	protected object $appApi;

	protected string $configuredAppId = 'live_transcription';
	protected bool $appApiEnabled = true;

	public function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig->method('getAppValueString')
			->with(Config::LIVE_TRANSCRIPTION_APP_ID)
			->willReturnCallback(fn (): string => $this->configuredAppId);

		$this->appManager = $this->createMock(IAppManager::class);
		$this->appManager->method('isEnabledForUser')
			->with('app_api')
			->willReturnCallback(fn (): bool => $this->appApiEnabled);

		$this->logger = $this->createMock(LoggerInterface::class);

		$this->appApi = new class {
			/** @var array<string, array{name: string, version: string, enabled: bool}> */
			public array $exApps = [];
			/** @var array<string, array<string, array|IResponse>> */
			public array $responses = [];
			/** @var list<array{appId: string, route: string, method: string}> */
			public array $requests = [];

			public function getExApp(string $appId): ?array {
				if (!isset($this->exApps[$appId])) {
					return null;
				}

				return ['appid' => $appId] + $this->exApps[$appId];
			}

			public function getExAppsList(string $list = 'enabled'): array {
				$exApps = [];
				foreach ($this->exApps as $appId => $exApp) {
					if ($list === 'enabled' && !$exApp['enabled']) {
						continue;
					}
					$exApps[] = ['id' => $appId, 'status' => []] + $exApp;
				}
				return $exApps;
			}

			public function exAppRequest(string $appId, string $route, ?string $userId = null, string $method = 'POST', array $params = []): array|IResponse {
				$this->requests[] = ['appId' => $appId, 'route' => $route, 'method' => $method];

				return $this->responses[$appId][$route] ?? ['error' => 'No response defined for ' . $appId . $route];
			}
		};

		$this->overwriteService(PublicFunctions::class, $this->appApi);
		$this->overwriteService(ExAppService::class, $this->appApi);

		$this->service = new LiveTranscriptionService(
			'admin',
			$this->appConfig,
			$this->appManager,
			$this->createMock(IFactory::class),
			$this->createMock(RoomService::class),
			$this->logger,
		);
	}

	public function tearDown(): void {
		$this->restoreService(PublicFunctions::class);
		$this->restoreService(ExAppService::class);

		parent::tearDown();
	}

	protected function addExApp(string $appId, string $name, bool $enabled = true, string $version = '1.0.0'): void {
		$this->appApi->exApps[$appId] = [
			'name' => $name,
			'version' => $version,
			'enabled' => $enabled,
		];
	}

	/**
	 * @param array{0: int, 1: mixed}|array{error: string} $response Status code
	 *                                                               and JSON body, or an AppAPI error
	 */
	protected function setResponse(string $appId, string $route, array $response): void {
		if (isset($response['error'])) {
			$this->appApi->responses[$appId][$route] = $response;
			return;
		}

		$mock = $this->createMock(IResponse::class);
		$mock->method('getStatusCode')->willReturn($response[0]);
		$mock->method('getBody')->willReturn(json_encode($response[1]));
		$mock->method('getHeader')->with('Content-Type')->willReturn('application/json');
		$this->appApi->responses[$appId][$route] = $mock;
	}

	protected static function getCapabilities(array $features = ['live_transcription'], string $version = '2.1.3'): array {
		return [
			'live_transcription' => [
				'version' => $version,
				'features' => $features,
				'live_transcription' => [
					'supported_languages' => ['en' => ['name' => 'English'], 'de' => ['name' => 'German']],
				],
			],
		];
	}

	public static function dataGetProviderStatus(): array {
		$languages = [
			'en' => ['name' => 'English', 'metadata' => []],
			'de' => ['name' => 'German', 'metadata' => []],
			'es' => ['name' => 'Spanish', 'metadata' => []],
		];

		return [
			'AppAPI disabled' => [
				false, ['Provider', true], null, null,
				['status' => 'app-api', 'name' => null, 'version' => null],
			],
			'external app not installed' => [
				true, null, null, null,
				['status' => 'not-found', 'name' => null, 'version' => null],
			],
			'external app disabled' => [
				true, ['Provider', false], null, null,
				['status' => 'disabled', 'name' => 'Provider', 'version' => '1.0.0'],
			],
			'request to external app fails' => [
				true, ['Provider', true], ['error' => 'cURL error 28'], [200, $languages],
				['status' => 'unreachable', 'name' => 'Provider', 'version' => '1.0.0'],
			],
			'external app answers capabilities with an error' => [
				true, ['Provider', true], [500, ['error' => 'broken']], [200, $languages],
				['status' => 'unreachable', 'name' => 'Provider', 'version' => '1.0.0'],
			],
			'capabilities without live transcription' => [
				true, ['Provider', true], [200, ['other_app' => ['version' => '1.2.3']]], [200, $languages],
				['status' => 'invalid-capabilities', 'name' => 'Provider', 'version' => '1.0.0'],
			],
			'capabilities but no languages endpoint' => [
				true, ['Provider', true], [200, self::getCapabilities()], [404, ['detail' => 'Not Found']],
				['status' => 'invalid-languages', 'name' => 'Provider', 'version' => '1.0.0'],
			],
			'capabilities but empty languages' => [
				true, ['Provider', true], [200, self::getCapabilities()], [200, []],
				['status' => 'invalid-languages', 'name' => 'Provider', 'version' => '1.0.0'],
			],
			'old provider without capabilities' => [
				true, ['Provider', true], [404, ['detail' => 'Not Found']], [200, $languages],
				['status' => 'no-capabilities', 'name' => 'Provider', 'version' => '1.0.0'],
			],
			'provider with capabilities and languages' => [
				true, ['Provider', true], [200, self::getCapabilities()], [200, $languages],
				['status' => 'ok', 'name' => 'Provider', 'version' => '2.1.3'],
			],
		];
	}

	/**
	 * @param array{0: string, 1: bool}|null $exApp Name and enabled state
	 */
	#[DataProvider('dataGetProviderStatus')]
	public function testGetProviderStatus(bool $appApiEnabled, ?array $exApp, ?array $capabilities, ?array $languages, array $expected): void {
		$this->configuredAppId = 'provider';
		$this->appApiEnabled = $appApiEnabled;

		if ($exApp !== null) {
			$this->addExApp('provider', $exApp[0], $exApp[1]);
		}
		if ($capabilities !== null) {
			$this->setResponse('provider', '/capabilities', $capabilities);
		}
		if ($languages !== null) {
			$this->setResponse('provider', '/api/v1/languages', $languages);
		}

		if (in_array($expected['status'], ['app-api', 'not-found', 'disabled'], true)) {
			// Known before sending any request to the external app, so nothing
			// should be logged by the failed requests
			$this->logger->expects($this->never())->method('error');
		}

		$this->assertSame(['appId' => 'provider'] + $expected, $this->service->getProviderStatus());
	}

	public function testGetProvidersOnlyReturnsAppsAnnouncingLiveTranscription(): void {
		$this->addExApp('cassini_live', 'Cassini Live');
		$this->setResponse('cassini_live', '/capabilities', [200, self::getCapabilities()]);

		$this->addExApp('disabled_provider', 'Disabled provider', false);
		$this->setResponse('disabled_provider', '/capabilities', [200, self::getCapabilities()]);

		$this->addExApp('no_capabilities', 'No capabilities');
		$this->setResponse('no_capabilities', '/capabilities', [404, ['detail' => 'Not Found']]);

		$this->addExApp('other_capabilities', 'Other capabilities');
		$this->setResponse('other_capabilities', '/capabilities', [200, ['other_capabilities' => ['features' => ['live_transcription']]]]);

		$this->addExApp('other_features', 'Other features');
		$this->setResponse('other_features', '/capabilities', [200, self::getCapabilities(['live_translation'])]);

		$this->addExApp('unreachable', 'Unreachable');
		$this->setResponse('unreachable', '/capabilities', ['error' => 'cURL error 28']);

		$this->addExApp('live_transcription', 'Live Transcription in Talk');
		$this->setResponse('live_transcription', '/capabilities', [200, self::getCapabilities()]);

		// Apps that are not a provider are expected, so nothing should be logged
		$this->logger->expects($this->never())->method('error');
		$this->logger->expects($this->never())->method('warning');

		$this->assertSame([
			['id' => 'cassini_live', 'name' => 'Cassini Live'],
			['id' => 'live_transcription', 'name' => 'Live Transcription in Talk'],
		], $this->service->getProviders());

		// Neither disabled apps nor the configured app are asked
		$this->assertSame(
			['cassini_live', 'no_capabilities', 'other_capabilities', 'other_features', 'unreachable'],
			array_column($this->appApi->requests, 'appId'),
		);
	}

	public function testGetProvidersIncludesConfiguredAppThatIsNoProvider(): void {
		$this->configuredAppId = 'no_provider';

		$this->addExApp('cassini_live', 'Cassini Live');
		$this->setResponse('cassini_live', '/capabilities', [200, self::getCapabilities()]);
		$this->addExApp('no_provider', 'No provider', false);

		$this->assertSame([
			['id' => 'cassini_live', 'name' => 'Cassini Live'],
			['id' => 'no_provider', 'name' => 'No provider'],
		], $this->service->getProviders());
	}

	public function testGetProvidersIncludesConfiguredAppThatIsNotInstalled(): void {
		$this->addExApp('cassini_live', 'Cassini Live');
		$this->setResponse('cassini_live', '/capabilities', [200, self::getCapabilities()]);

		$this->assertSame([
			['id' => 'live_transcription', 'name' => 'live_transcription'],
			['id' => 'cassini_live', 'name' => 'Cassini Live'],
		], $this->service->getProviders());
	}

	public function testGetProvidersWithoutAppApi(): void {
		$this->appApiEnabled = false;
		$this->addExApp('cassini_live', 'Cassini Live');

		$this->assertSame([
			['id' => 'live_transcription', 'name' => 'live_transcription'],
		], $this->service->getProviders());
		$this->assertSame([], $this->appApi->requests);
	}
}

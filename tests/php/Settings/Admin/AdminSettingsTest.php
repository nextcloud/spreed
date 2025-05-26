<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Settings\Admin;

use OCA\Talk\Config;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Settings\Admin\AdminSettings;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Support\Subscription\IRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AdminSettingsTest extends TestCase {
	protected Config&MockObject $talkConfig;
	protected IConfig&MockObject $serverConfig;
	protected IAppConfig&MockObject $appConfig;
	protected IInitialState&MockObject $initialState;
	protected ICacheFactory&MockObject $cacheFactory;
	protected IGroupManager&MockObject $groupManager;
	protected MatterbridgeManager&MockObject $matterbridgeManager;
	protected IRegistry&MockObject $subscription;
	protected IUserSession&MockObject $userSession;
	protected IL10N&MockObject $l10n;
	protected IFactory&MockObject $l10nFactory;
	protected ?AdminSettings $admin = null;

	public function setUp(): void {
		parent::setUp();

		$this->talkConfig = $this->createMock(Config::class);
		$this->serverConfig = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->matterbridgeManager = $this->createMock(MatterbridgeManager::class);
		$this->subscription = $this->createMock(IRegistry::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10nFactory = $this->createMock(IFactory::class);

		$this->admin = $this->getAdminSettings();
	}

	/**
	 * @param string[] $methods
	 * @return AdminSettings|MockObject
	 */
	protected function getAdminSettings(array $methods = []): AdminSettings {
		if (empty($methods)) {
			return new AdminSettings(
				$this->talkConfig,
				$this->serverConfig,
				$this->appConfig,
				$this->initialState,
				$this->cacheFactory,
				$this->groupManager,
				$this->matterbridgeManager,
				$this->subscription,
				$this->userSession,
				$this->l10n,
				$this->l10nFactory
			);
		}

		return $this->getMockBuilder(AdminSettings::class)
			->setConstructorArgs([
				$this->talkConfig,
				$this->serverConfig,
				$this->appConfig,
				$this->initialState,
				$this->cacheFactory,
				$this->groupManager,
				$this->matterbridgeManager,
				$this->subscription,
				$this->userSession,
				$this->l10n,
				$this->l10nFactory,
			])
			->onlyMethods($methods)
			->getMock();
	}

	public function testGetSection(): void {
		$admin = $this->getAdminSettings();
		$this->assertNotEmpty($admin->getSection());
	}

	public function testGetPriority(): void {
		$admin = $this->getAdminSettings();
		$this->assertEquals(0, $admin->getPriority());
	}

	public function testGetForm(): void {
		$admin = $this->getAdminSettings([
			'initGeneralSettings',
			'initAllowedGroups',
			'initStunServers',
			'initTurnServers',
			'initSignalingServers',
			'initRequestSignalingServerTrial',
		]);

		$admin->expects($this->once())
			->method('initGeneralSettings');
		$admin->expects($this->once())
			->method('initAllowedGroups');
		$admin->expects($this->once())
			->method('initStunServers');
		$admin->expects($this->once())
			->method('initTurnServers');
		$admin->expects($this->once())
			->method('initSignalingServers');
		$admin->expects($this->once())
			->method('initRequestSignalingServerTrial');

		$form = $admin->getForm();
		$this->assertSame('settings/admin-settings', $form->getTemplateName());
		$this->assertSame('', $form->getRenderAs());
		$this->assertCount(0, $form->getParams());
	}

	public function testInitStunServers(): void {
		$this->talkConfig->expects($this->once())
			->method('getStunServers')
			->willReturn(['getStunServers']);
		$this->serverConfig->expects($this->once())
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(true);

		$i = 0;
		$expectedCalls = [
			['stun_servers', ['getStunServers']],
			['has_internet_connection', true],
		];
		$this->initialState->expects($this->exactly(2))
			->method('provideInitialState')
			->willReturnCallback(function () use ($expectedCalls, &$i): void {
				$this->assertArrayHasKey($i, $expectedCalls);
				$this->assertSame($expectedCalls[$i], func_get_args());
				$i++;
			});

		$admin = $this->getAdminSettings();
		self::invokePrivate($admin, 'initStunServers');
	}

	public function testInitTurnServers(): void {
		$this->talkConfig->expects($this->once())
			->method('getTurnServers')
			->willReturn(['getTurnServers']);

		$this->initialState->expects($this->once())
			->method('provideInitialState')
			->with('turn_servers', ['getTurnServers']);

		$admin = $this->getAdminSettings();
		self::invokePrivate($admin, 'initTurnServers');
	}
}

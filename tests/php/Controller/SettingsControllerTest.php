<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Config;
use OCA\Talk\Controller\SettingsController;
use OCA\Talk\Settings\BeforePreferenceSetEventListener;
use OCP\AppFramework\Services\IAppConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class SettingsControllerTest extends TestCase {
	protected IConfig&MockObject $config;
	protected IAppConfig&MockObject $appConfig;
	protected IGroupManager&MockObject $groupManager;
	protected IEventDispatcher&MockObject $dispatcher;
	protected SettingsController $controller;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->controller = new SettingsController(
			'spreed',
			$this->createMock(IRequest::class),
			$this->config,
			$this->appConfig,
			$this->groupManager,
			$this->createMock(BeforePreferenceSetEventListener::class),
			'admin',
			$this->dispatcher,
		);
	}

	public static function dataSetSIPSettings(): array {
		return [
			['old-secret', 'new-secret', 'changed'],
			['same-secret', 'same-secret', 'unchanged'],
		];
	}

	#[DataProvider('dataSetSIPSettings')]
	public function testSetSIPSettingsLogsCriticalAction(string $oldSecret, string $newSecret, string $expected): void {
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('sip-users');
		$this->groupManager->method('get')->willReturnMap([
			['sip-users', $group],
			['unknown', null],
		]);

		$this->appConfig->method('getAppValueString')->willReturnMap([
			[Config::SIP_BRIDGE_SHARED_SECRET, $oldSecret],
		]);

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'spreed.sip_settings.updated'
				&& $event->getLogMessage() === 'SIP bridge settings were changed: groups "{sipGroups}", shared secret {sharedSecret}'
				&& $event->getParameters() === ['sipGroups' => 'sip-users', 'sharedSecret' => $expected]));

		$this->controller->setSIPSettings(['sip-users', 'unknown'], 'Dial-in info', $newSecret);
	}
}

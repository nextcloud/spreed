<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Tests\php\Settings\Admin;

use OCA\Talk\Config;
use OCA\Talk\Service\CommandService;
use OCA\Talk\Settings\Admin\AdminSettings;
use OCP\IConfig;
use OCP\IInitialStateService;
use PHPUnit\Framework\MockObject\MockObject;

class AdminSettingsTest extends \Test\TestCase {

	/** @var Config|MockObject */
	protected $talkConfig;
	/** @var IConfig|MockObject */
	protected $serverConfig;
	/** @var CommandService|MockObject */
	protected $commandService;
	/** @var IInitialStateService|MockObject */
	protected $initialState;
	/** @var StunServer */
	protected $admin;

	public function setUp(): void {
		parent::setUp();

		$this->talkConfig = $this->createMock(Config::class);
		$this->serverConfig = $this->createMock(IConfig::class);
		$this->commandService = $this->createMock(CommandService::class);
		$this->initialState = $this->createMock(IInitialStateService::class);

		$this->admin = new AdminSettings(
			$this->talkConfig,
			$this->serverConfig,
			$this->commandService,
			$this->initialState
		);
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
				$this->commandService,
				$this->initialState
			);
		}

		return $this->getMockBuilder(AdminSettings::class)
			->setConstructorArgs([
				$this->talkConfig,
				$this->serverConfig,
				$this->commandService,
				$this->initialState,
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
			'initCommands',
			'initStunServers',
			'initTurnServers',
			'initSignalingServers'
		]);

		$admin->expects($this->once())
			->method('initGeneralSettings');
		$admin->expects($this->once())
			->method('initAllowedGroups');
		$admin->expects($this->once())
			->method('initCommands');
		$admin->expects($this->once())
			->method('initStunServers');
		$admin->expects($this->once())
			->method('initTurnServers');
		$admin->expects($this->once())
			->method('initSignalingServers');

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

		$this->initialState->expects($this->exactly(2))
			->method('provideInitialState')
			->withConsecutive(
				['talk', 'stun_servers', ['getStunServers']],
				['talk', 'has_internet_connection', true]
			);

		$admin = $this->getAdminSettings();
		self::invokePrivate($admin, 'initStunServers');
	}

	public function testInitTurnServers(): void {
		$this->talkConfig->expects($this->once())
			->method('getTurnServers')
			->willReturn(['getTurnServers']);

		$this->initialState->expects($this->once())
			->method('provideInitialState')
			->with('talk', 'turn_servers', ['getTurnServers']);

		$admin = $this->getAdminSettings();
		self::invokePrivate($admin, 'initTurnServers');
	}
}

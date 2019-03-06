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

namespace OCA\Spreed\Tests\php\Settings\Admin;

use OCA\Spreed\Config;
use OCA\Spreed\Settings\Admin\TurnServer;
use OCP\IInitialStateService;
use PHPUnit\Framework\MockObject\MockObject;

class TurnServerTest extends \Test\TestCase {

	/** @var Config|MockObject */
	protected $config;
	/** @var IInitialStateService|MockObject */
	protected $initialState;
	/** @var TurnServer */
	protected $admin;

	public function setUp() {
		parent::setUp();

		$this->config = $this->createMock(Config::class);
		$this->initialState = $this->createMock(IInitialStateService::class);

		$this->admin = new TurnServer($this->config, $this->initialState);
	}

	public function testGetSection(): void {
		$this->assertNotEmpty($this->admin->getSection());
	}

	public function testGetPriority(): void {
		$this->assertGreaterThan(0, $this->admin->getPriority());
	}

	public function testGetForm(): void {
		$this->config->expects($this->once())
			->method('getTurnServers')
			->willReturn(['getTurnServers']);

		$this->initialState->expects($this->once())
			->method('provideInitialState')
			->with('talk', 'turn_servers', ['getTurnServers']);

		$form = $this->admin->getForm();
		$this->assertSame('settings/admin/turn-server', $form->getTemplateName());
		$this->assertSame('', $form->getRenderAs());
		$this->assertCount(0, $form->getParams());
	}
}

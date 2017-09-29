<?php
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

use OCA\Spreed\Settings\Admin\StunServer;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;

class StunServerTest extends \Test\TestCase {

	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;
	/** @var StunServer */
	protected $admin;

	public function setUp() {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);

		$this->admin = new StunServer($this->config);
	}

	public function testGetSection() {
		$this->assertInternalType('string', $this->admin->getSection());
		$this->assertNotEmpty($this->admin->getSection());
	}

	public function testGetPriority() {
		$this->assertInternalType('int', $this->admin->getPriority());
		$this->assertGreaterThan(0, $this->admin->getPriority());
	}

	public function testGetForm() {
		$form = $this->admin->getForm();
		$this->assertInstanceOf(TemplateResponse::class, $form);
		$this->assertSame('', $form->getRenderAs());

		$params = $form->getParams();
		$this->assertArrayHasKey('stunServer', $params);
	}
}

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

use OCA\Spreed\Settings\Admin\Section;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;

class SectionTest extends \Test\TestCase {

	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	protected $url;
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	protected $l;
	/** @var Section */
	protected $admin;

	public function setUp() {
		parent::setUp();

		$this->url = $this->createMock(IURLGenerator::class);
		$this->l = $this->createMock(IL10N::class);

		$this->admin = new Section($this->url, $this->l);
	}

	public function testGetID() {
		$this->assertInternalType('string', $this->admin->getID());
		$this->assertNotEmpty($this->admin->getID());
	}

	public function testGetName() {
		$this->l->expects($this->exactly(2))
			->method('t')
			->with('Video calls')
			->willReturnArgument(0);
		$this->assertInternalType('string', $this->admin->getName());
		$this->assertNotEmpty($this->admin->getName());
	}

	public function testGetIcon() {
		$this->url->expects($this->exactly(2))
			->method('imagePath')
			->with('spreed', 'app-dark.svg')
			->willReturn('apps/spreed/img/app-dark.svg');
		$this->assertInternalType('string', $this->admin->getIcon());
		$this->assertNotEmpty($this->admin->getIcon());
	}

	public function testGetPriority() {
		$this->assertInternalType('int', $this->admin->getPriority());
		$this->assertGreaterThan(0, $this->admin->getPriority());
	}
}

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

use OCA\Talk\Settings\Admin\Section;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class SectionTest extends TestCase {
	protected IURLGenerator&MockObject $url;
	protected IL10N&MockObject $l;
	protected ?Section $admin = null;

	public function setUp(): void {
		parent::setUp();

		$this->url = $this->createMock(IURLGenerator::class);
		$this->l = $this->createMock(IL10N::class);

		$this->admin = new Section($this->url, $this->l);
	}

	public function testGetID(): void {
		$this->assertNotEmpty($this->admin->getID());
	}

	public function testGetName(): void {
		$this->l->expects($this->once())
			->method('t')
			->with('Talk')
			->willReturnArgument(0);
		$this->assertNotEmpty($this->admin->getName());
	}

	public function testGetIcon(): void {
		$this->url->expects($this->once())
			->method('imagePath')
			->with('spreed', 'app-dark.svg')
			->willReturn('apps/spreed/img/app-dark.svg');
		$this->assertNotEmpty($this->admin->getIcon());
	}

	public function testGetPriority(): void {
		$this->assertGreaterThan(0, $this->admin->getPriority());
	}
}

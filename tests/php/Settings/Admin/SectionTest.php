<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

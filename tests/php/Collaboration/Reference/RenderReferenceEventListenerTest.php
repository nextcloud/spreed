<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Collaboration\Reference;

use OCA\Talk\Collaboration\Reference\RenderReferenceEventListener;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\Util;
use Test\TestCase;

class RenderReferenceEventListenerTest extends TestCase {
	protected RenderReferenceEventListener $listener;

	public function setUp(): void {
		parent::setUp();

		$this->listener = new RenderReferenceEventListener();
	}

	public function testHandleIgnoresUnrelatedEvents(): void {
		$scriptsBefore = Util::getScripts();

		$this->listener->handle($this->createMock(Event::class));

		self::assertSame($scriptsBefore, Util::getScripts());
	}

	public function testHandleLoadsTheReferenceScriptOnRenderReferenceEvent(): void {
		$this->listener->handle(new RenderReferenceEvent());

		self::assertContains('spreed/js/talk-reference', Util::getScripts());
	}
}

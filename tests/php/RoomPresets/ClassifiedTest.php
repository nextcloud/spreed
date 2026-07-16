<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\RoomPresets;

use OCA\Talk\Room;
use OCA\Talk\RoomPresets\Classified;
use OCA\Talk\RoomPresets\Parameter;
use OCA\Talk\Webinary;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ClassifiedTest extends TestCase {
	protected IL10N&MockObject $l10n;
	protected Classified $preset;

	public function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->preset = new Classified($this->l10n);
	}

	public function testIdentifier(): void {
		$this->assertSame('classified', Classified::getIdentifier());
	}

	public function testName(): void {
		$this->assertSame('Classified conversation', $this->preset->getName());
	}

	public function testParametersLockDownTheConversation(): void {
		$parameters = $this->preset->getParameters();

		$this->assertSame(Room::TYPE_GROUP, $parameters[Parameter::ROOM_TYPE->value], 'Classified conversations must not be public');
		$this->assertSame(Room::LISTABLE_NONE, $parameters[Parameter::LISTABLE->value], 'Classified conversations must not be openly joinable');
		$this->assertSame(Webinary::SIP_DISABLED, $parameters[Parameter::SIP_ENABLED->value], 'Classified conversations must not allow SIP');
		$this->assertSame(3600, $parameters[Parameter::MESSAGE_EXPIRATION->value], 'Classified conversations expire messages after one hour');
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix;

use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Service\NotificationLevelService;
use OCA\Talk\Participant;
use OCA\Talk\Service\ParticipantService;
use Test\TestCase;

class NotificationLevelServiceTest extends TestCase {
	private NotificationLevelService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->service = new NotificationLevelService($this->createMock(ParticipantService::class));
	}

	private function room(bool $direct): MatrixRoom {
		$room = new MatrixRoom();
		$room->setMatrixRoomId('!r:hs');
		$room->setIsDirect($direct);
		return $room;
	}

	public function testDefaults(): void {
		self::assertSame(Participant::NOTIFY_MENTION, $this->service->levelFor($this->room(false), null));
		self::assertSame(Participant::NOTIFY_ALWAYS, $this->service->levelFor($this->room(true), null));
	}

	public function testRoomRuleWins(): void {
		$muted = ['global' => ['room' => [['rule_id' => '!r:hs', 'enabled' => true, 'actions' => ['dont_notify']]]]];
		self::assertSame(Participant::NOTIFY_NEVER, $this->service->levelFor($this->room(true), $muted));

		$emptyActions = ['global' => ['room' => [['rule_id' => '!r:hs', 'enabled' => true, 'actions' => []]]]];
		self::assertSame(Participant::NOTIFY_NEVER, $this->service->levelFor($this->room(false), $emptyActions));

		$loud = ['global' => ['room' => [['rule_id' => '!r:hs', 'enabled' => true, 'actions' => ['notify', ['set_tweak' => 'sound', 'value' => 'default']]]]]];
		self::assertSame(Participant::NOTIFY_ALWAYS, $this->service->levelFor($this->room(false), $loud));

		$disabled = ['global' => ['room' => [['rule_id' => '!r:hs', 'enabled' => false, 'actions' => ['dont_notify']]]]];
		self::assertSame(Participant::NOTIFY_MENTION, $this->service->levelFor($this->room(false), $disabled));

		$otherRoom = ['global' => ['room' => [['rule_id' => '!other:hs', 'enabled' => true, 'actions' => ['dont_notify']]]]];
		self::assertSame(Participant::NOTIFY_MENTION, $this->service->levelFor($this->room(false), $otherRoom));
	}
}

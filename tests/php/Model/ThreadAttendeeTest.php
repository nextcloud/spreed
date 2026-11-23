<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Model;

use OCA\Talk\Model\ThreadAttendee;
use OCA\Talk\Participant;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class ThreadAttendeeTest extends TestCase {
	public static function dataJsonSerializeNotificationLevel(): array {
		return [
			'default' => [Participant::NOTIFY_DEFAULT, Participant::NOTIFY_DEFAULT],
			'never' => [Participant::NOTIFY_NEVER, Participant::NOTIFY_NEVER],
			'direct mentions' => [Participant::NOTIFY_MENTION_DIRECT, Participant::NOTIFY_MENTION_DIRECT],
			'too low' => [-1, Participant::NOTIFY_DEFAULT],
			'too high' => [5, Participant::NOTIFY_MENTION_DIRECT],
		];
	}

	#[DataProvider('dataJsonSerializeNotificationLevel')]
	public function testJsonSerializeNotificationLevel(int $level, int $expected): void {
		$threadAttendee = new ThreadAttendee();
		$threadAttendee->setNotificationLevel($level);
		$this->assertSame(['notificationLevel' => $expected], $threadAttendee->jsonSerialize());
	}
}

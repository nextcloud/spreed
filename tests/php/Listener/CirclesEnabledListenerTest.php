<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Talk\Listener\CirclesEnabledListener;
use OCA\Talk\Service\TeamRoomSyncService;
use OCP\App\Events\AppEnableEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CirclesEnabledListenerTest extends TestCase {
	public static function dataEnabledApp(): array {
		return [
			'circles' => ['circles', 1],
			'other app' => ['files', 0],
		];
	}

	#[DataProvider('dataEnabledApp')]
	public function testEnablingCirclesResyncsAllTeamRooms(string $appId, int $resyncs): void {
		/** @var TeamRoomSyncService&MockObject $syncService */
		$syncService = $this->createMock(TeamRoomSyncService::class);
		$syncService->expects($this->exactly($resyncs))->method('queueFullResync');

		(new CirclesEnabledListener($syncService))->handle(new AppEnableEvent($appId));
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Talk\Events\BeforeParticipantModifiedEvent;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Listener\RestrictStartingCalls;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

#[Group('DB')]
class RestrictStartingCallsTest extends TestCase {
	protected IConfig&MockObject $serverConfig;
	protected ParticipantService&MockObject $participantService;
	protected ?RestrictStartingCalls $listener = null;

	public function setUp(): void {
		parent::setUp();

		$this->serverConfig = $this->createMock(IConfig::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->listener = new RestrictStartingCalls($this->serverConfig, $this->participantService);
	}

	public static function dataCheckStartCallPermissions(): array {
		return [
			'default blocked' => [Room::TYPE_PUBLIC, '', false, false, true],

			'allowed password request' => [Room::TYPE_PUBLIC, Room::OBJECT_TYPE_VIDEO_VERIFICATION, false, false, false],
			'call active already' => [Room::TYPE_PUBLIC, '', false, true, false],
			'user has permissions' => [Room::TYPE_PUBLIC, '', true, false, false],
			'user has permissions & call' => [Room::TYPE_PUBLIC, '', true, true, false],
		];
	}

	#[DataProvider('dataCheckStartCallPermissions')]
	public function testCheckStartCallPermissions(int $roomType, string $roomObjectType, bool $canStart, bool $hasParticipants, bool $throws): void {
		$room = $this->createMock(Room::class);
		$room->method('getType')
			->willReturn($roomType);
		$room->method('getObjectType')
			->willReturn($roomObjectType);

		$participant = $this->createMock(Participant::class);
		$participant->method('canStartCall')
			->with($this->serverConfig)
			->willReturn($canStart);

		$this->participantService->method('hasActiveSessionsInCall')
			->willReturn($hasParticipants);

		$event = new BeforeParticipantModifiedEvent(
			$room,
			$participant,
			'inCall',
			Participant::FLAG_IN_CALL,
			Participant::FLAG_DISCONNECTED
		);

		if ($throws) {
			$this->expectException(ForbiddenException::class);
		}

		$this->overwriteService(RestrictStartingCalls::class, $this->listener);
		$this->listener->handle($event);
		$this->restoreService(RestrictStartingCalls::class);

		if (!$throws) {
			self::assertTrue(true);
		}
	}
}

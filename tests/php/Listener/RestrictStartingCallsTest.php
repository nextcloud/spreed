<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Tests\php\Listener;

use OCA\Talk\Events\BeforeParticipantModifiedEvent;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Listener\RestrictStartingCalls;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

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

	/**
	 * @dataProvider dataCheckStartCallPermissions
	 */
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

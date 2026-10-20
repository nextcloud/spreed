<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Middleware;

use OCA\Talk\Controller\AEnvironmentAwareOCSController;
use OCA\Talk\Controller\RoomController;
use OCA\Talk\Middleware\Attribute\MatrixSupported;
use OCA\Talk\Middleware\Exceptions\MatrixUnsupportedFeatureException;
use OCA\Talk\Middleware\InjectionMiddleware;
use OCA\Talk\Room;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class InjectionMiddlewareTest extends TestCase {
	public static function dataCheckMatrixSupport(): array {
		return [
			'matrix conversation' => [Room::OBJECT_TYPE_MATRIX, true],
			'other conversation' => ['', false],
		];
	}

	#[DataProvider('dataCheckMatrixSupport')]
	public function testCheckMatrixSupport(string $objectType, bool $throws): void {
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn($objectType);
		$controller = $this->createMock(AEnvironmentAwareOCSController::class);
		$controller->method('getRoom')->willReturn($room);
		$middleware = $this->getMockBuilder(InjectionMiddleware::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();

		if ($throws) {
			$this->expectException(MatrixUnsupportedFeatureException::class);
		}
		self::invokePrivate($middleware, 'checkMatrixSupport', [$controller]);
		$this->addToAssertionCount(1);
	}

	public function testMatrixSupportedModerationOfRooms(): void {
		$supported = [];
		foreach ((new \ReflectionClass(RoomController::class))->getMethods() as $method) {
			if ($method->getAttributes(MatrixSupported::class) !== []) {
				$supported[] = $method->getName();
			}
		}
		sort($supported);

		self::assertSame([
			'addParticipantToRoom',
			'demoteModerator',
			'promoteModerator',
			'removeAttendeeFromRoom',
			'renameRoom',
			'setDescription',
		], $supported, 'Moderation in Matrix conversations has to be applied to the Matrix room');
	}
}

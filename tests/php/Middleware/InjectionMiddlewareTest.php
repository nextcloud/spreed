<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Middleware;

use OCA\Talk\Controller\AEnvironmentAwareOCSController;
use OCA\Talk\Controller\RoomController;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Manager;
use OCA\Talk\Middleware\Attribute\AllowExternalCallService;
use OCA\Talk\Middleware\Attribute\MatrixSupported;
use OCA\Talk\Middleware\Exceptions\MatrixUnsupportedFeatureException;
use OCA\Talk\Middleware\Exceptions\NotAModeratorException;
use OCA\Talk\Middleware\InjectionMiddleware;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ChecksumVerificationService;
use OCA\Talk\Service\ParticipantService;
use OCP\IRequest;
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

	public static function dataGetExternalCallServiceOwner(): array {
		return [
			'invalid checksum' => [false, Room::OBJECT_TYPE_EXTERNAL_CALL, true, UnauthorizedException::class],
			'other conversation' => [true, '', true, RoomNotFoundException::class],
			'not an owner' => [true, Room::OBJECT_TYPE_EXTERNAL_CALL, false, NotAModeratorException::class],
			'valid' => [true, Room::OBJECT_TYPE_EXTERNAL_CALL, true, null],
		];
	}

	#[DataProvider('dataGetExternalCallServiceOwner')]
	public function testGetExternalCallServiceOwner(bool $validChecksum, string $objectType, bool $isOwner, ?string $exception): void {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnMap([
			['owner', '', 'alice'],
			['token', null, 'abcdef'],
		]);
		$checksumVerificationService = $this->createMock(ChecksumVerificationService::class);
		$checksumVerificationService->method('validateExternalCallServiceRequest')
			->willReturn($validChecksum);
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn($objectType);
		$manager = $this->createMock(Manager::class);
		$manager->method('getRoomForUserByToken')->with('abcdef', 'alice')->willReturn($room);
		$participant = $this->createMock(Participant::class);
		$participant->method('isOwner')->willReturn($isOwner);
		$participantService = $this->createMock(ParticipantService::class);
		$participantService->method('getParticipant')->with($room, 'alice', false)->willReturn($participant);

		$arguments = [];
		foreach ((new \ReflectionClass(InjectionMiddleware::class))->getConstructor()->getParameters() as $parameter) {
			$type = (string)$parameter->getType();
			$arguments[] = match ($type) {
				IRequest::class => $request,
				ChecksumVerificationService::class => $checksumVerificationService,
				Manager::class => $manager,
				ParticipantService::class => $participantService,
				'?string' => null,
				default => $this->createMock($type),
			};
		}
		$middleware = new InjectionMiddleware(...$arguments);

		$controller = $this->createMock(AEnvironmentAwareOCSController::class);
		if ($exception !== null) {
			$this->expectException($exception);
			$controller->expects(self::never())->method('setParticipant');
		} else {
			$controller->expects(self::once())->method('setRoom')->with($room);
			$controller->expects(self::once())->method('setParticipant')->with($participant);
		}
		self::invokePrivate($middleware, 'getExternalCallServiceOwner', [$controller]);
	}

	public function testExternalCallServiceEndpoints(): void {
		$allowed = [];
		foreach ((new \ReflectionClass(RoomController::class))->getMethods() as $method) {
			if ($method->getAttributes(AllowExternalCallService::class) !== []) {
				$allowed[] = $method->getName();
			}
		}

		self::assertSame(['renameRoom'], $allowed, 'Only explicitly reviewed endpoints may be used by the external call service');
	}
}

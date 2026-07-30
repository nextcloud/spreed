<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Federation;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\FederationRestrictionException;
use OCA\Talk\Federation\RestrictionValidator;
use OCA\Talk\Room;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Federation\ICloudId;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class RestrictionValidatorTest extends TestCase {
	protected AddressHandler&MockObject $addressHandler;
	protected IAppManager&MockObject $appManager;
	protected Config&MockObject $talkConfig;
	protected IAppConfig&MockObject $appConfig;
	protected LoggerInterface&MockObject $logger;
	protected RestrictionValidator $validator;

	public function setUp(): void {
		parent::setUp();

		$this->addressHandler = $this->createMock(AddressHandler::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->talkConfig = $this->createMock(Config::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->validator = new RestrictionValidator(
			$this->addressHandler,
			$this->appManager,
			$this->talkConfig,
			$this->appConfig,
			$this->logger,
		);
	}

	protected function createCloudId(): ICloudId&MockObject {
		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getUser')->willReturn('remote-user');
		$cloudId->method('getRemote')->willReturn('https://remote.example.tld');
		$cloudId->method('getId')->willReturn('remote-user@remote.example.tld');
		return $cloudId;
	}

	public function testIsAllowedToInviteThrowsForClassifiedRoom(): void {
		$room = $this->createMock(Room::class);
		$room->method('isClassified')->willReturn(true);

		// The rejection must not depend on how federation is configured, so the
		// classified check has to happen before any of it is consulted
		$this->appConfig->expects(self::never())->method('getAppValueBool');
		$this->talkConfig->expects(self::never())->method('isFederationEnabledForUserId');

		$this->expectException(FederationRestrictionException::class);
		$this->expectExceptionMessage('classified');

		$this->validator->isAllowedToInvite(
			$this->createMock(IUser::class),
			$this->createCloudId(),
			$room,
		);
	}

	public function testIsAllowedToInviteAllowsRegularRoom(): void {
		$room = $this->createMock(Room::class);
		$room->method('isClassified')->willReturn(false);

		$this->appConfig->method('getAppValueBool')->willReturnMap([
			['federation_outgoing_enabled', true, false, true],
			['federation_only_trusted_servers', false, false, false],
		]);
		$this->talkConfig->method('isFederationEnabledForUserId')->willReturn(true);

		$this->validator->isAllowedToInvite(
			$this->createMock(IUser::class),
			$this->createCloudId(),
			$room,
		);

		// No exception means the invite is allowed
		self::assertTrue(true);
	}

	public function testIsAllowedToInviteWithoutRoomIsUnaffected(): void {
		$this->appConfig->method('getAppValueBool')->willReturnMap([
			['federation_outgoing_enabled', true, false, true],
			['federation_only_trusted_servers', false, false, false],
		]);
		$this->talkConfig->method('isFederationEnabledForUserId')->willReturn(true);

		$this->validator->isAllowedToInvite(
			$this->createMock(IUser::class),
			$this->createCloudId(),
		);

		self::assertTrue(true);
	}
}

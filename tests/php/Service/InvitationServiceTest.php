<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Config;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\Room;
use OCA\Talk\Service\InvitationService;
use OCA\Talk\Service\ParticipantService;
use OCP\App\IAppManager;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IPhoneNumberUtil;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEmailValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class InvitationServiceTest extends TestCase {
	protected IAppManager&MockObject $appManager;
	protected ICloudIdManager&MockObject $cloudIdManager;
	protected IGroupManager&MockObject $groupManager;
	protected IPhoneNumberUtil&MockObject $phoneNumberUtil;
	protected IUserManager&MockObject $userManager;
	protected FederationManager&MockObject $federationManager;
	protected ParticipantService&MockObject $participantService;
	protected IConfig&MockObject $serverConfig;
	protected Config&MockObject $talkConfig;
	protected IEmailValidator&MockObject $emailValidator;
	protected InvitationService $service;

	public function setUp(): void {
		parent::setUp();

		$this->appManager = $this->createMock(IAppManager::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->phoneNumberUtil = $this->createMock(IPhoneNumberUtil::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->federationManager = $this->createMock(FederationManager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->serverConfig = $this->createMock(IConfig::class);
		$this->talkConfig = $this->createMock(Config::class);
		$this->emailValidator = $this->createMock(IEmailValidator::class);

		$this->service = new InvitationService(
			$this->appManager,
			$this->cloudIdManager,
			$this->groupManager,
			$this->phoneNumberUtil,
			$this->userManager,
			$this->federationManager,
			$this->participantService,
			$this->serverConfig,
			$this->talkConfig,
			$this->emailValidator,
		);
	}

	public function testPhoneInvitationsAreRejectedWhenCreatingClassifiedConversation(): void {
		$currentUser = $this->createMock(IUser::class);
		// SIP is fully available, only the classified flag must reject the number
		$this->talkConfig->method('isSIPConfigured')->willReturn(true);
		$this->talkConfig->method('canUserDialOutSIP')->willReturn(true);
		$this->phoneNumberUtil->expects(self::never())
			->method('convertToStandardFormat');

		$invitationList = $this->service->validateInvitations(
			['phones' => ['+491601234567']],
			$currentUser,
			isClassified: true,
		);

		self::assertSame([], $invitationList->getPhoneNumbers());
		self::assertTrue($invitationList->hasInvalidInvitations());
		self::assertSame(['+491601234567'], $invitationList->getInvalidList()['phones']);
	}

	public function testPhoneInvitationsAreRejectedForClassifiedRoom(): void {
		$currentUser = $this->createMock(IUser::class);
		$room = $this->createMock(Room::class);
		$room->method('isClassified')->willReturn(true);
		$this->talkConfig->method('isSIPConfigured')->willReturn(true);
		$this->talkConfig->method('canUserDialOutSIP')->willReturn(true);
		$this->phoneNumberUtil->expects(self::never())
			->method('convertToStandardFormat');

		$invitationList = $this->service->validateInvitations(
			['phones' => ['+491601234567']],
			$currentUser,
			$room,
		);

		self::assertSame([], $invitationList->getPhoneNumbers());
		self::assertSame(['+491601234567'], $invitationList->getInvalidList()['phones']);
	}

	public function testFederatedUserInvitationsAreRejectedWhenCreatingClassifiedConversation(): void {
		$currentUser = $this->createMock(IUser::class);
		// Federation is available, only the classified flag must reject the invite
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->cloudIdManager->expects(self::never())
			->method('resolveCloudId');
		$this->federationManager->expects(self::never())
			->method('isAllowedToInvite');

		$invitationList = $this->service->validateInvitations(
			['federated_users' => ['remote-user@remote.example.tld']],
			$currentUser,
			isClassified: true,
		);

		self::assertSame([], $invitationList->getFederatedUsers());
		self::assertTrue($invitationList->hasInvalidInvitations());
		self::assertSame(['remote-user@remote.example.tld'], $invitationList->getInvalidList()['federated_users']);
	}

	public function testFederatedUserInvitationsAreRejectedForClassifiedRoom(): void {
		$currentUser = $this->createMock(IUser::class);
		$room = $this->createMock(Room::class);
		$room->method('isClassified')->willReturn(true);
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->federationManager->expects(self::never())
			->method('isAllowedToInvite');

		$invitationList = $this->service->validateInvitations(
			['federated_users' => ['remote-user@remote.example.tld']],
			$currentUser,
			$room,
		);

		self::assertSame([], $invitationList->getFederatedUsers());
		self::assertSame(['remote-user@remote.example.tld'], $invitationList->getInvalidList()['federated_users']);
	}

	public function testFederatedUserInvitationsAreAcceptedForRegularConversation(): void {
		$currentUser = $this->createMock(IUser::class);
		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getId')->willReturn('remote-user@remote.example.tld');
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->cloudIdManager->method('resolveCloudId')->willReturn($cloudId);

		$invitationList = $this->service->validateInvitations(
			['federated_users' => ['remote-user@remote.example.tld']],
			$currentUser,
		);

		self::assertSame(['remote-user@remote.example.tld' => $cloudId], $invitationList->getFederatedUsers());
		self::assertFalse($invitationList->hasInvalidInvitations());
	}

	public function testPhoneInvitationsAreAcceptedForRegularConversation(): void {
		$currentUser = $this->createMock(IUser::class);
		$this->talkConfig->method('isSIPConfigured')->willReturn(true);
		$this->talkConfig->method('canUserDialOutSIP')->willReturn(true);
		$this->serverConfig->method('getSystemValueString')->willReturn('DE');
		$this->phoneNumberUtil->method('convertToStandardFormat')
			->willReturn('+491601234567');

		$invitationList = $this->service->validateInvitations(
			['phones' => ['+491601234567']],
			$currentUser,
		);

		self::assertSame(['+491601234567' => '+491601234567'], $invitationList->getPhoneNumbers());
		self::assertFalse($invitationList->hasInvalidInvitations());
	}
}

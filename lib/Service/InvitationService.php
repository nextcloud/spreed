<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Config;
use OCA\Talk\Exceptions\FederationRestrictionException;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Model\InvitationList;
use OCA\Talk\Room;
use OCP\App\IAppManager;
use OCP\Federation\ICloudIdManager;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IPhoneNumberUtil;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEmailValidator;

class InvitationService {
	public function __construct(
		private readonly IAppManager $appManager,
		private readonly ICloudIdManager $cloudIdManager,
		private readonly IGroupManager $groupManager,
		private readonly IPhoneNumberUtil $phoneNumberUtil,
		private readonly IUserManager $userManager,
		private readonly FederationManager $federationManager,
		private readonly ParticipantService $participantService,
		private readonly IConfig $serverConfig,
		private readonly Config $talkConfig,
		private readonly IEmailValidator $emailValidator,
	) {
	}

	/**
	 * @param bool $isClassified Whether the invitations target a classified conversation.
	 *                           Only needed at creation time, when the room does not exist yet;
	 *                           otherwise it is read from $room.
	 */
	public function validateInvitations(array $participants, IUser $currentUser, ?Room $room = null, bool $isClassified = false): InvitationList {
		$isClassified = $isClassified || $room?->isClassified() === true;

		$invitationList = new InvitationList();
		if (!empty($participants['users'])) {
			$this->validateUserInvitations($invitationList, $participants['users']);
		}
		if (!empty($participants['emails'])) {
			$this->validateEmailInvitations($invitationList, $participants['emails']);
		}
		if (!empty($participants['groups'])) {
			$this->validateGroupInvitations($invitationList, $participants['groups']);
		}
		if (!empty($participants['teams'])) {
			$this->validateTeamInvitations($invitationList, $participants['teams'], $currentUser);
		}
		if (!empty($participants['federated_users'])) {
			$this->validateFederatedUserInvitations($invitationList, $participants['federated_users'], $currentUser, $isClassified);
		}
		if (!empty($participants['phones'])) {
			$this->validatePhoneInvitations($invitationList, $participants['phones'], $currentUser, $room, $isClassified);
		}
		return $invitationList;
	}

	/**
	 * @param list<string> $userIds
	 */
	protected function validateUserInvitations(InvitationList $invitationList, array $userIds): void {
		$invalidUsers = $validUsers = [];
		foreach ($userIds as $userId) {
			if ($userId === MatterbridgeManager::BRIDGE_BOT_USERID) {
				$invalidUsers[] = $userId;
				continue;
			}

			$user = $this->userManager->get($userId);
			if ($user instanceof IUser) {
				$validUsers[$userId] = $user;
			} else {
				$invalidUsers[] = $userId;
			}
		}

		$invitationList->setUserResults($validUsers, $invalidUsers);
	}

	/**
	 * @param list<string> $emails
	 */
	protected function validateEmailInvitations(InvitationList $invitationList, array $emails): void {
		$invalidEmails = $validEmails = [];
		foreach ($emails as $email) {
			if ($this->emailValidator->isValid($email)) {
				$validEmails[$email] = strtolower($email);
			} else {
				$invalidEmails[] = $email;
			}
		}
		$invitationList->setEmailResults($validEmails, $invalidEmails);
	}

	/**
	 * @param list<string> $groupIds
	 */
	protected function validateGroupInvitations(InvitationList $invitationList, array $groupIds): void {
		$invalidGroups = $validGroups = [];

		foreach ($groupIds as $groupId) {
			$group = $this->groupManager->get($groupId);
			if ($group instanceof IGroup) {
				$validGroups[$groupId] = $group;
			} else {
				$invalidGroups[] = $groupId;
			}
		}
		$invitationList->setGroupResults($validGroups, $invalidGroups);
	}

	/**
	 * @param list<string> $teamIds
	 */
	protected function validateTeamInvitations(InvitationList $invitationList, array $teamIds, IUser $currentUser): void {
		if (!$this->appManager->isEnabledForUser('circles')) {
			$invitationList->setTeamResults([], $teamIds);
			return;
		}

		$invalidTeams = $validTeams = [];

		foreach ($teamIds as $teamId) {
			try {
				$team = $this->participantService->getCircle($teamId, $currentUser->getUID());
				$validTeams[$teamId] = $team;
			} catch (\Exception) {
				$invalidTeams[] = $teamId;
			}
		}

		$invitationList->setTeamResults($validTeams, $invalidTeams);
	}

	/**
	 * @param list<string> $cloudIds
	 */
	protected function validateFederatedUserInvitations(InvitationList $invitationList, array $cloudIds, IUser $currentUser, bool $isClassified): void {
		if ($isClassified) {
			// The remote server would receive the conversation and its messages
			$invitationList->setFederatedUserResults([], $cloudIds);
			return;
		}

		if (!$this->talkConfig->isFederationEnabled()) {
			$invitationList->setFederatedUserResults([], $cloudIds);
			return;
		}

		$invalidCloudIds = $validCloudIds = [];
		foreach ($cloudIds as $cloudIdString) {
			try {
				$cloudId = $this->cloudIdManager->resolveCloudId($cloudIdString);
				$this->federationManager->isAllowedToInvite($currentUser, $cloudId);
				$validCloudIds[$cloudIdString] = $cloudId;
			} catch (\InvalidArgumentException|FederationRestrictionException) {
				$invalidCloudIds[] = $cloudIdString;
			}
		}
		$invitationList->setFederatedUserResults($validCloudIds, $invalidCloudIds);
	}

	/**
	 * @param list<string> $phoneNumbers
	 */
	protected function validatePhoneInvitations(InvitationList $invitationList, array $phoneNumbers, IUser $currentUser, ?Room $room, bool $isClassified): void {
		if ($isClassified) {
			// Classified conversations can not dial out
			$invitationList->setPhoneNumberResults([], $phoneNumbers);
			return;
		}

		if (!$this->talkConfig->isSIPConfigured() || !$this->talkConfig->canUserDialOutSIP($currentUser)) {
			$invitationList->setPhoneNumberResults([], $phoneNumbers);
			return;
		}

		if ($room instanceof Room
			&& (preg_match(Room::SIP_INCOMPATIBLE_REGEX, $room->getToken())
				|| !in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true))) {
			$invitationList->setPhoneNumberResults([], $phoneNumbers);
			return;
		}

		$phoneRegion = $this->serverConfig->getSystemValueString('default_phone_region');
		if ($phoneRegion === '') {
			$phoneRegion = null;
		}

		$invalidPhoneNumbers = [];
		$validPhoneNumbers = [];

		foreach ($phoneNumbers as $phoneNumber) {
			$formattedNumber = $this->phoneNumberUtil->convertToStandardFormat($phoneNumber, $phoneRegion);
			if ($formattedNumber === null) {
				$invalidPhoneNumbers[] = $phoneNumber;
			} else {
				$validPhoneNumbers[$phoneNumber] = $formattedNumber;
			}
		}

		$invitationList->setPhoneNumberResults($validPhoneNumbers, $invalidPhoneNumbers);
	}
}

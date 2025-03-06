<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Circles\Model\Circle;
use OCP\Federation\ICloudId;
use OCP\IGroup;
use OCP\IUser;

class InvitationList {

	/** @var array<string, IUser> */
	protected array $validUsers = [];
	/** @var list<string> */
	protected array $invalidUsers = [];

	/** @var array<string, ICloudId> */
	protected array $validFederatedUsers = [];
	/** @var list<string> */
	protected array $invalidFederatedUsers = [];

	/** @var array<string, IGroup> */
	protected array $validGroups = [];
	/** @var list<string> */
	protected array $invalidGroups = [];

	/** @var array<string, Circle> */
	protected array $validTeams = [];
	/** @var list<string> */
	protected array $invalidTeams = [];

	/** @var array<string, string> */
	protected array $validEmails = [];
	/** @var list<string> */
	protected array $invalidEmails = [];

	/** @var array<string, string> */
	protected array $validPhoneNumbers = [];
	/** @var list<string> */
	protected array $invalidPhoneNumbers = [];

	/**
	 * @param array<string, IUser> $valid
	 * @param list<string> $invalid
	 */
	public function setUserResults(array $valid, array $invalid): void {
		$this->validUsers = $valid;
		$this->invalidUsers = $invalid;
	}

	/**
	 * @param array<string, ICloudId> $valid
	 * @param list<string> $invalid
	 */
	public function setFederatedUserResults(array $valid, array $invalid): void {
		$this->validFederatedUsers = $valid;
		$this->invalidFederatedUsers = $invalid;
	}

	/**
	 * @param array<string, IGroup> $valid
	 * @param list<string> $invalid
	 */
	public function setGroupResults(array $valid, array $invalid): void {
		$this->validGroups = $valid;
		$this->invalidGroups = $invalid;
	}

	/**
	 * @param array<string, Circle> $valid
	 * @param list<string> $invalid
	 */
	public function setTeamResults(array $valid, array $invalid): void {
		$this->validTeams = $valid;
		$this->invalidTeams = $invalid;
	}

	/**
	 * @param array<string, string> $valid
	 * @param list<string> $invalid
	 */
	public function setEmailResults(array $valid, array $invalid): void {
		$this->validEmails = $valid;
		$this->invalidEmails = $invalid;
	}

	/**
	 * @param array<string, string> $valid
	 * @param list<string> $invalid
	 */
	public function setPhoneNumberResults(array $valid, array $invalid): void {
		$this->validPhoneNumbers = $valid;
		$this->invalidPhoneNumbers = $invalid;
	}

	/**
	 * @return array<string, IUser>
	 */
	public function getUsers(): array {
		return $this->validUsers;
	}

	/**
	 * @return array<string, ICloudId>
	 */
	public function getFederatedUsers(): array {
		return $this->validFederatedUsers;
	}

	/**
	 * @return array<string, IGroup>
	 */
	public function getGroup(): array {
		return $this->validGroups;
	}

	/**
	 * @return array<string, Circle>
	 */
	public function getTeams(): array {
		return $this->validTeams;
	}

	/**
	 * @return array<string, string>
	 */
	public function getEmails(): array {
		return $this->validEmails;
	}

	/**
	 * @return array<string, string>
	 */
	public function getPhoneNumbers(): array {
		return $this->validPhoneNumbers;
	}

	/**
	 * @return array<'users'|'federated_users'|'groups'|'emails'|'phones'|'teams', list<string>>
	 */
	public function getInvalidList(): array {
		$response = [
			'users' => $this->invalidUsers,
			'federated_users' => $this->invalidFederatedUsers,
			'groups' => $this->invalidGroups,
			'teams' => $this->invalidTeams,
			'emails' => $this->invalidEmails,
			'phones' => $this->invalidPhoneNumbers,
		];
		return array_filter($response);
	}

	public function hasValidInvitations(): bool {
		return !empty($this->validUsers)
			|| !empty($this->validFederatedUsers)
			|| !empty($this->validGroups)
			|| !empty($this->validTeams)
			|| !empty($this->validEmails)
			|| !empty($this->validPhoneNumbers);
	}

	public function hasInvalidInvitations(): bool {
		return !empty($this->invalidUsers)
			|| !empty($this->invalidFederatedUsers)
			|| !empty($this->invalidGroups)
			|| !empty($this->invalidTeams)
			|| !empty($this->invalidEmails)
			|| !empty($this->invalidPhoneNumbers);
	}
}

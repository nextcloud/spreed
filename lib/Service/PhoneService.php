<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Model\PhoneNumber;
use OCA\Talk\Model\PhoneNumberMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUser;
use OCP\IUserManager;
use OCP\User\IAvailabilityCoordinator;

class PhoneService {

	public function __construct(
		protected IUserManager $userManager,
		protected IAvailabilityCoordinator $availabilityCoordinator,
		protected PhoneNumberMapper $mapper,
	) {
	}

	/**
	 * Get user ID to call
	 *
	 * Internally falling back to the OOO replacement,
	 * if one is defined that has a phone number and is not OOO itself.
	 *
	 * @throws DoesNotExistException
	 */
	public function getAccountToCallForPhoneNumber(string $phoneNumber): PhoneNumber {
		$entity = $this->mapper->findByPhoneNumber($phoneNumber);

		$user = $this->userManager->get($entity->getActorId());
		if (!$user instanceof IUser) {
			throw new DoesNotExistException('Invalid user');
		}

		$outOfOffice = $this->availabilityCoordinator->getCurrentOutOfOfficeData($user);
		if ($outOfOffice === null
			|| $outOfOffice->getReplacementUserId() === null
			|| !$this->availabilityCoordinator->isInEffect($outOfOffice)) {
			return $entity;
		}

		$replacementUser = $this->userManager->get($outOfOffice->getReplacementUserId());
		if (!$replacementUser instanceof IUser) {
			// Replacement is wrong, fall back to original user
			return $entity;
		}

		$outOfOffice = $this->availabilityCoordinator->getCurrentOutOfOfficeData($replacementUser);
		if ($outOfOffice !== null
			&& $this->availabilityCoordinator->isInEffect($outOfOffice)) {
			// Replacement is also OOO, fall back to original user
			return $entity;
		}

		$entities = $this->mapper->findByUser($replacementUser->getUID());
		if (empty($entities)) {
			// Replacement has no phone number, fall back to original user
			return $entity;
		}

		return array_shift($entities);
	}

	/**
	 * @return list<PhoneNumber>
	 */
	public function findByUser(string $userId): array {
		return $this->mapper->findByUser($userId);
	}

	/**
	 * @return list<PhoneNumber>
	 */
	public function findByPhoneNumbers(array $phoneNumbers): array {
		return $this->mapper->findByPhoneNumbers($phoneNumbers);
	}

	public function deleteByPhoneNumber(string $phoneNumber): void {
		$this->mapper->deleteByPhoneNumber($phoneNumber);
	}

	public function deleteByUser(string $userId): void {
		$this->mapper->deleteByUser($userId);
	}
}

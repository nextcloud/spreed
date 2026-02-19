<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Model\PhoneNumber;
use OCA\Talk\Model\PhoneNumberMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use OCP\IPhoneNumberUtil;
use OCP\IUser;
use OCP\IUserManager;
use OCP\User\IAvailabilityCoordinator;

class PhoneNumberValidation {

	public function __construct(
		protected IPhoneNumberUtil $phoneNumberUtil,
		protected IConfig $config,
	) {
	}

	/**
	 * Validate input as a phone number
	 *
	 * - Local number: allow
	 * - International number:
	 *   1. Replace leading 00 with +
	 *   2. Check if still valid international number
	 *      a. If valid, strip + and allow
	 *      b. If invalid, throw
	 * @throws \InvalidArgumentException When the number is invalid
	 */
	public function validateNumber(string $phoneNumber): string {

		if (
			// Not an internation number
			!str_starts_with($phoneNumber, '00')
			// And matches a local number or dial-through
			&& preg_match('/^[0-9]{1,20}$/', $phoneNumber)
		) {
			return $phoneNumber;
		}

		// Replace double leading zero with +
		if (str_starts_with($phoneNumber, '00')) {
			$phoneNumber = '+' . substr($phoneNumber, 2);
		}

		$defaultRegion = $this->config->getSystemValueString('default_phone_region') ?: null;
		$standardPhoneNumber = $this->phoneNumberUtil->convertToStandardFormat($phoneNumber, $defaultRegion);

		if ($standardPhoneNumber === null) {
			throw new \InvalidArgumentException();
		}

		if (str_starts_with($standardPhoneNumber, '+')) {
			return substr($standardPhoneNumber, 1);
		}

		return $standardPhoneNumber;
	}
}

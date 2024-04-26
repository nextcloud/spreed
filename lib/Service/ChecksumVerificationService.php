<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Exceptions\UnauthorizedException;

class ChecksumVerificationService {
	/**
	 * Check if the current request is coming from an allowed backend.
	 *
	 * The backend servers are sending custom headers "Talk-{{FEATURE}}-Random"
	 * containing at least 32 bytes random data, and the header
	 * "Talk-{{FEATURE}}-Checksum", which is the SHA256-HMAC of the random data
	 * and the body of the request, calculated with the shared secret from the
	 * configuration.
	 *
	 * @param string $random
	 * @param string $checksum
	 * @param string $secret
	 * @param string $data
	 * @return bool True if the request is from the backend and valid, false if not from SIP bridge
	 * @throws UnauthorizedException when the request tried to authenticate as backend but is not valid
	 */
	public function validateRequest(string $random, string $checksum, string $secret, string $data): bool {
		if ($random === '' && $checksum === '') {
			return false;
		}

		if (strlen($random) < 32) {
			throw new UnauthorizedException('Invalid random provided');
		}

		if ($checksum === '') {
			throw new UnauthorizedException('Invalid checksum provided');
		}

		if ($secret === '') {
			throw new UnauthorizedException('No shared SIP secret provided');
		}

		$hash = hash_hmac('sha256', $random . $data, $secret);

		if (hash_equals($hash, strtolower($checksum))) {
			return true;
		}

		throw new UnauthorizedException('Invalid HMAC provided');
	}
}

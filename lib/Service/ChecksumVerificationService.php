<?php

declare(strict_types=1);
/*
 * @copyright Copyright (c) 2022 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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

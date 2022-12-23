<?php
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

class SIPBridgeService {
	/**
	 * Check if the current request is coming from an allowed backend.
	 *
	 * The SIP bridge is sending the custom header "Talk-SIPBridge-Random"
	 * containing at least 32 bytes random data, and the header
	 * "Talk-SIPBridge-Checksum", which is the SHA256-HMAC of the random data
	 * and the body of the request, calculated with the shared secret from the
	 * configuration.
	 *
	 * @param string $random
	 * @param string $checksum
	 * @param string $secret
	 * @param string $token
	 * @return bool True if the request is from the SIP bridge and valid, false if not from SIP bridge
	 * @throws UnauthorizedException when the request tried to sign as SIP bridge but is not valid
	 */
	public function validateSIPBridgeRequest(string $random, string $checksum, string $secret, string $token): bool {
		if ($random === '' && $checksum === '') {
			return false;
		}

		if (strlen($random) < 32) {
			throw new UnauthorizedException('Invalid random provided');
		}

		if (empty($checksum)) {
			throw new UnauthorizedException('Invalid checksum provided');
		}

		if (empty($secret)) {
			throw new UnauthorizedException('No shared SIP secret provided');
		}
		$hash = hash_hmac('sha256', $random . $token, $secret);

		if (hash_equals($hash, strtolower($checksum))) {
			return true;
		}

		throw new UnauthorizedException('Invalid HMAC provided');
	}
}

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Generate an RFC 4122 version 4 (random) UUID.
 *
 * `crypto.randomUUID()` is only exposed in secure contexts (HTTPS or
 * localhost). When Talk is served over plain HTTP it is `undefined`, which
 * makes `crypto.randomUUID()` throw. In that case we fall back to
 * `crypto.getRandomValues()`, which is available in insecure contexts too,
 * and assemble the UUID manually.
 *
 * @return a random UUID in the canonical `xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx` form
 */
function randomUuid(): string {
	if (typeof crypto.randomUUID === 'function') {
		return crypto.randomUUID()
	}

	// Fallback for insecure contexts (plain HTTP): crypto.randomUUID is not
	// available, but crypto.getRandomValues still is.
	const bytes = crypto.getRandomValues(new Uint8Array(16))

	// Set the version to 4 (0100) in the high nibble of byte 6
	bytes[6] = (bytes[6] & 0x0f) | 0x40
	// Set the variant to RFC 4122 (10xx) in the two high bits of byte 8
	bytes[8] = (bytes[8] & 0x3f) | 0x80

	const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0'))

	return [
		hex.slice(0, 4).join(''),
		hex.slice(4, 6).join(''),
		hex.slice(6, 8).join(''),
		hex.slice(8, 10).join(''),
		hex.slice(10, 16).join(''),
	].join('-')
}

export {
	randomUuid,
}

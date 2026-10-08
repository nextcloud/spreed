<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client;

use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Model\LoginResult;

/**
 * Matrix Client-Server API façade. One instance per (homeserver, access token).
 */
final class Client {
	public const PREFIX = '/_matrix/client/v3';

	public function __construct(
		private readonly Transport $transport,
	) {
	}

	public function withAccessToken(#[\SensitiveParameter] ?string $token): self {
		return new self($this->transport->withAccessToken($token));
	}

	/**
	 * m.login.password
	 *
	 * @throws MatrixException
	 */
	public function loginWithPassword(string $user, #[\SensitiveParameter] string $password, string $initialDeviceDisplayName): LoginResult {
		return LoginResult::fromArray($this->transport->withAccessToken(null)->post(self::PREFIX . '/login', [
			'type' => 'm.login.password',
			'identifier' => ['type' => 'm.id.user', 'user' => $user],
			'password' => $password,
			'initial_device_display_name' => $initialDeviceDisplayName,
		]));
	}

	/**
	 * Invalidates the access token and removes the device
	 *
	 * @throws MatrixException
	 */
	public function logout(): void {
		$this->transport->post(self::PREFIX . '/logout');
	}
}

<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OC\Authentication\Token {

	use OCP\Authentication\Token\IToken as OCPIToken;

	interface IToken extends \JsonSerializable {
		public const TEMPORARY_TOKEN = 0;
		public const DO_NOT_REMEMBER = 0;

	}

	interface IProvider {
		public function generateToken(string $token,
			string $uid,
			string $loginName,
			?string $password,
			string $name,
			int $type = OCPIToken::TEMPORARY_TOKEN,
			int $remember = OCPIToken::DO_NOT_REMEMBER,
			?array $scope = null,
		): IToken;

		public function invalidateToken(string $token);

		public function invalidateTokenById(string $uid, int $id);

		public function getTokenByUser(string $uid): array;
	}
}

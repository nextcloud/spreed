<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\DataObjects;

class AccountId {

	public function __construct(
		private string $accountId,
	) {
	}

	public function get(): string {
		return $this->accountId;
	}
}

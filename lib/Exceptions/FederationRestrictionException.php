<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Talk\Exceptions;

class FederationRestrictionException extends \InvalidArgumentException {
	public const REASON_CLOUD_ID = 'cloud-id';
	public const REASON_FEDERATION = 'federation';
	public const REASON_OUTGOING = 'outgoing';
	public const REASON_TRUSTED_SERVERS = 'trusted-servers';

	/**
	 * @param self::REASON_* $reason
	 */
	public function __construct(
		protected readonly string $reason,
	) {
		parent::__construct($this->reason);
	}

	/**
	 * @return self::REASON_*
	 */
	public function getReason(): string {
		return $this->reason;
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCP\EventDispatcher\Event;

class BeforeTurnServersGetEvent extends Event {
	/**
	 * @param list<array{schemes?: string, server: string, protocols: string, username?: string, password?: string, secret?: string}> $servers
	 */
	public function __construct(
		protected array $servers,
	) {
		parent::__construct();
	}

	/**
	 * @return list<array{schemes?: string, server: string, protocols: string, username?: string, password?: string, secret?: string}>
	 */
	public function getServers(): array {
		return $this->servers;
	}

	/**
	 * @param list<array{schemes?: string, server: string, protocols: string, username?: string, password?: string, secret?: string}> $servers
	 */
	public function setServers(array $servers): void {
		$this->servers = $servers;
	}
}

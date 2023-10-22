<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Joachim Bauch <mail@joachim-bauch.de>
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
 *
 */

namespace OCA\Talk\Events;

use OCP\EventDispatcher\Event;

class BeforeTurnServersGetEvent extends Event {
	/**
	 * @param list<array{schemes?: string, server: string, protocols: string, username?: string, password?: string, secret?: string}> $servers
	 */
	public function __construct(
		protected array $servers
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

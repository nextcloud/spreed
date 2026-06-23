<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCP\EventDispatcher\Event;

class BotUninstallEvent extends Event {
	public function __construct(
		private readonly string $secret,
		private readonly string $url,
	) {
		parent::__construct();
	}

	public function getSecret(): string {
		return $this->secret;
	}

	public function getUrl(): string {
		return $this->url;
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Model\Bot;
use OCP\EventDispatcher\Event;

class BotInstallEvent extends Event {
	public function __construct(
		protected string $name,
		protected string $secret,
		protected string $url,
		protected string $description = '',
		protected int $features = Bot::FEATURE_WEBHOOK | Bot::FEATURE_RESPONSE,
	) {
		parent::__construct();
	}

	public function getName(): string {
		return $this->name;
	}

	public function getSecret(): string {
		return $this->secret;
	}

	public function getUrl(): string {
		return $this->url;
	}

	public function getDescription(): string {
		return $this->description;
	}

	public function getFeatures(): int {
		return $this->features;
	}
}

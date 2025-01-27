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
		protected ?int $features = null,
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
		if ($this->features !== null) {
			return $this->features;
		}
		if (str_starts_with($this->url, Bot::URL_APP_PREFIX)) {
			return Bot::FEATURE_EVENT;
		}
		return Bot::FEATURE_WEBHOOK | Bot::FEATURE_RESPONSE;
	}
}

<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

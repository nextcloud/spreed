<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Room;

class BeforeSignalingRoomPropertiesSentEvent extends ARoomEvent {

	public function __construct(
		Room $room,
		protected ?string $userId,
		protected array $properties,
	) {
		parent::__construct($room);
	}

	public function getUserId(): ?string {
		return $this->userId;
	}

	public function getProperties(): array {
		return $this->properties;
	}

	public function setProperty(string $property, $data): void {
		$this->properties[$property] = $data;
	}

	public function unsetProperty(string $property): void {
		unset($this->properties[$property]);
	}
}

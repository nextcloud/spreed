<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Room;

abstract class APermissionsModifiedEvent extends ARoomModifiedEvent {
	public function __construct(
		Room $room,
		string $property,
		string|int $newValue,
		string|int|null $oldValue,
		protected string $method,
		protected int $permissions,
		protected bool $resetCustomPermissions,
	) {
		parent::__construct(
			$room,
			$property,
			$newValue,
			$oldValue,
		);
	}

	public function getMethod(): string {
		return $this->method;
	}

	public function getPermissions(): int {
		return $this->permissions;
	}

	public function resetCustomPermissions(): bool {
		return $this->resetCustomPermissions;
	}
}

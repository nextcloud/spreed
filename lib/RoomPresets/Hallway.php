<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

use OCA\Talk\Room;

class Hallway implements IPreset {
	#[\Override]
	public static function getDefault(Parameter $parameter): ?int {
		return match ($parameter) {
			// Users but no guest users (by default)
			Parameter::LISTABLE => Room::LISTABLE_USERS,
			// If you were not there, you were not there …
			Parameter::MESSAGE_EXPIRATION => 3600,
			default => null,
		};
	}
}

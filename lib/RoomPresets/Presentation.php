<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\RecordingService;

class Presentation implements IPreset {
	#[\Override]
	public static function getDefault(Parameter $parameter): ?int {
		return match ($parameter) {
			Parameter::MENTION_PERMISSIONS => Room::MENTION_PERMISSIONS_MODERATORS,
			Parameter::PERMISSIONS => Attendee::PERMISSIONS_CUSTOM
				| Attendee::PERMISSIONS_CALL_JOIN
				| Attendee::PERMISSIONS_CHAT,
			Parameter::RECORDING_CONSENT => RecordingService::CONSENT_REQUIRED_YES,
			default => null,
		};
	}
}

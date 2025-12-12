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
use OCA\Talk\Webinary;

class DefaultPreset implements IPreset {
	public static function getDefault(Parameter $parameter): int {
		return match ($parameter) {
			Parameter::ROOM_TYPE => Room::TYPE_GROUP,
			Parameter::READ_ONLY => Room::READ_WRITE,
			Parameter::LISTABLE => Room::LISTABLE_NONE,
			Parameter::MESSAGE_EXPIRATION => 0,
			Parameter::LOBBY_STATE => Webinary::LOBBY_NONE,
			Parameter::SIP_ENABLED => Webinary::SIP_DISABLED,
			Parameter::PERMISSIONS => Attendee::PERMISSIONS_DEFAULT,
			Parameter::RECORDING_CONSENT => RecordingService::CONSENT_REQUIRED_NO,
			Parameter::MENTION_PERMISSIONS => Room::MENTION_PERMISSIONS_EVERYONE,
		};
	}
}

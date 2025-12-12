<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

enum Parameter: string {
	// Not forceable
	case ROOM_TYPE = 'roomType';
	case READ_ONLY = 'readOnly';
	case LOBBY_STATE = 'lobbyState';
	case RECORDING_CONSENT = 'recordingConsent';

	// Forceable
	case LISTABLE = 'listable';
	case MESSAGE_EXPIRATION = 'messageExpiration';
	case SIP_ENABLED = 'sipEnabled';
	case PERMISSIONS = 'permissions';
	case MENTION_PERMISSIONS = 'mentionPermissions';
}

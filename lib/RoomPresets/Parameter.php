<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;


enum Parameter: string {
	case ROOM_TYPE = 'roomType';
	case READ_ONLY = 'readOnly';
	case LISTABLE = 'listable';
	case MESSAGE_EXPIRATION = 'messageExpiration';
	case LOBBY_STATE = 'lobbyState';
	case SIP_ENABLED = 'sipEnabled';
	case PERMISSIONS = 'permissions';
	case RECORDING_CONSENT = 'recordingConsent';
	case MENTION_PERMISSIONS = 'mentionPermissions';
}

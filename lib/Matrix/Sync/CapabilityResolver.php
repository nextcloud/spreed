<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Sync;

use Nextcloud\Matrix\Model\Member;
use Nextcloud\Matrix\Model\PowerLevels;
use Nextcloud\Matrix\Model\RoomState;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Model\Attendee;

/**
 * Computes what a Matrix room supports, once per room (stored as JSON) and
 * once per viewing user (power-level dependent part, computed on read).
 */
class CapabilityResolver {
	/**
	 * Room-level capabilities from room state and homeserver features.
	 * @return array<string, mixed>
	 */
	public function forRoom(RoomState $state, ?Homeserver $homeserver, bool $isDirect): array {
		$versions = $homeserver?->getVersions() ?? [];
		$specVersions = array_values(array_filter($versions['versions'] ?? [], 'is_string'));
		$unstable = is_array($versions['unstable_features'] ?? null) ? $versions['unstable_features'] : [];

		$supportsSpec = static function (string $minimum) use ($specVersions): bool {
			foreach ($specVersions as $v) {
				if (version_compare(ltrim($v, 'v'), $minimum, '>=')) {
					return true;
				}
			}
			return false;
		};

		return [
			'roomVersion' => $state->roomVersion,
			'encrypted' => $state->isEncrypted(),
			'encryptionSupported' => $homeserver?->getAllowE2ee() ?? true,
			'isDirect' => $isDirect,
			'joinRule' => $state->joinRule,
			'canonicalAlias' => $state->canonicalAlias,
			'upgradedTo' => $state->tombstoneReplacement,
			'threads' => $supportsSpec('1.4') || ($unstable['org.matrix.msc3440.stable'] ?? false) === true,
			'reactions' => true,
			'edits' => true,
			'redactions' => true,
			'upload' => $homeserver?->getAllowUpload() ?? true,
			'calls' => false,
			'polls' => false,
			'locationSharing' => false,
			'voiceMessages' => false,
			'lobby' => false,
			'listable' => false,
			'sipEnabled' => false,
			'breakoutRooms' => false,
			'recording' => false,
			'messageExpiration' => false,
			'guests' => false,
			'maxMessageLength' => 32000,
			'serverName' => $homeserver?->getServerName(),
		];
	}

	/**
	 * User-level capabilities from power levels; merged into the room-level set on read.
	 * @return array<string, bool>
	 */
	public function forUser(PowerLevels $powerLevels, string $mxid, string $membership, bool $encryptedRoom, bool $e2eeAllowed = true): array {
		$joined = $membership === Member::JOIN;
		$blockedByPolicy = $encryptedRoom && !$e2eeAllowed;
		return [
			'canSend' => $joined && !$blockedByPolicy && $powerLevels->canSendMessage($mxid),
			'canSendReason' => $blockedByPolicy ? 'e2ee-disabled' : ($joined ? null : 'not-joined'),
			'canReact' => $joined && !$blockedByPolicy && $powerLevels->canSendEvent($mxid, 'm.reaction'),
			'canEdit' => $joined && !$blockedByPolicy && $powerLevels->canSendMessage($mxid),
			'canDeleteOwn' => $joined && $powerLevels->canSendEvent($mxid, 'm.room.redaction'),
			'canDeleteOthers' => $joined && $powerLevels->canDo($mxid, 'redact'),
			'canInvite' => $joined && $powerLevels->canDo($mxid, 'invite'),
			'canKick' => $joined && $powerLevels->canDo($mxid, 'kick'),
			'canBan' => $joined && $powerLevels->canDo($mxid, 'ban'),
			'canRename' => $joined && $powerLevels->canSendEvent($mxid, 'm.room.name', true),
			'canSetDescription' => $joined && $powerLevels->canSendEvent($mxid, 'm.room.topic', true),
			'canSetAvatar' => $joined && $powerLevels->canSendEvent($mxid, 'm.room.avatar', true),
			'canPromote' => $joined && $powerLevels->canSendEvent($mxid, 'm.room.power_levels', true),
			'isModerator' => $powerLevels->isModerator($mxid),
			'isAdmin' => $powerLevels->isAdmin($mxid),
		];
	}

	/**
	 * Talk participant type derived from the power level.
	 */
	public function participantType(PowerLevels $powerLevels, string $mxid, string $creator): int {
		if ($powerLevels->isAdmin($mxid) && $mxid === $creator) {
			return \OCA\Talk\Participant::OWNER;
		}
		if ($powerLevels->isModerator($mxid)) {
			return \OCA\Talk\Participant::MODERATOR;
		}
		return \OCA\Talk\Participant::USER;
	}

	/**
	 * Attendee permission bits: chat/react only, never any call bit.
	 */
	public function attendeePermissions(PowerLevels $powerLevels, string $mxid, string $membership, bool $encryptedRoom, bool $e2eeAllowed = true): int {
		$permissions = Attendee::PERMISSIONS_CUSTOM;
		$blockedByPolicy = $encryptedRoom && !$e2eeAllowed;
		if ($membership === Member::JOIN && !$blockedByPolicy && $powerLevels->canSendMessage($mxid)) {
			$permissions |= Attendee::PERMISSIONS_CHAT;
		}
		if ($membership === Member::JOIN && !$blockedByPolicy && $powerLevels->canSendEvent($mxid, 'm.reaction')) {
			$permissions |= Attendee::PERMISSIONS_REACT;
		}
		return $permissions;
	}

	/** Default permissions of a Matrix conversation: chat + react, no calls. */
	public function roomDefaultPermissions(): int {
		return Attendee::PERMISSIONS_CUSTOM | Attendee::PERMISSIONS_CHAT | Attendee::PERMISSIONS_REACT;
	}

	/** Hydrate the stored room capabilities with the per-user part. */
	public function merge(MatrixRoom $matrixRoom, ?string $mxid, string $membership): array {
		$capabilities = $matrixRoom->getCapabilitiesArray();
		$powerLevels = new PowerLevels($matrixRoom->getPowerLevelsArray(), $matrixRoom->getCreator());
		if ($mxid !== null) {
			$capabilities += $this->forUser($powerLevels, $mxid, $membership, $matrixRoom->getEncrypted(), (bool)($capabilities['encryptionSupported'] ?? true));
		}
		$capabilities['matrixRoomId'] = $matrixRoom->getMatrixRoomId();
		return $capabilities;
	}
}

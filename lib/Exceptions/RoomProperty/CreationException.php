<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Exceptions\RoomProperty;

class CreationException extends \InvalidArgumentException {
	public const REASON_AVATAR = 'avatar';
	public const REASON_DESCRIPTION = 'description';
	public const REASON_LISTABLE = 'listable';
	public const REASON_LOBBY = 'lobby';
	public const REASON_LOBBY_TIMER = 'lobby-timer';
	public const REASON_MESSAGE_EXPIRATION = 'message-expiration';
	public const REASON_MENTION_PERMISSIONS = 'mention-permissions';
	public const REASON_NAME = 'name';
	public const REASON_OBJECT = 'object';
	public const REASON_OBJECT_ID = 'object-id';
	public const REASON_OBJECT_TYPE = 'object-type';
	public const REASON_PERMISSIONS = 'permissions';
	public const REASON_READ_ONLY = 'read-only';
	public const REASON_RECORDING_CONSENT = 'recording-consent';
	public const REASON_SIP_ENABLED = 'sip-enabled';
	public const REASON_TYPE = 'type';

	/**
	 * @param self::REASON_* $reason
	 */
	public function __construct(
		protected string $reason,
	) {
		parent::__construct($reason);
	}

	/**
	 * @return self::REASON_*
	 */
	public function getReason(): string {
		return $this->reason;
	}
}

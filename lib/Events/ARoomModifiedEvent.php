<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Participant;
use OCA\Talk\Room;

abstract class ARoomModifiedEvent extends ARoomEvent {
	public const PROPERTY_ACTIVE_SINCE = 'activeSince';
	public const PROPERTY_AVATAR = 'avatar';
	public const PROPERTY_BREAKOUT_ROOM_MODE = 'breakoutRoomMode';
	public const PROPERTY_BREAKOUT_ROOM_STATUS = 'breakoutRoomStatus';
	/** @deprecated */
	public const PROPERTY_CALL_PERMISSIONS = 'callPermissions';
	public const PROPERTY_CALL_RECORDING = 'callRecording';
	public const PROPERTY_DEFAULT_PERMISSIONS = 'defaultPermissions';
	public const PROPERTY_DESCRIPTION = 'description';
	public const PROPERTY_IN_CALL = 'inCall';
	public const PROPERTY_LISTABLE = 'listable';
	public const PROPERTY_LOBBY = 'lobby';
	public const PROPERTY_LIVE_TRANSCRIPTION_LANGUAGE_ID = 'liveTranscriptionLanguageId';
	public const PROPERTY_MESSAGE_EXPIRATION = 'messageExpiration';
	public const PROPERTY_MENTION_PERMISSIONS = 'mentionPermissions';
	public const PROPERTY_NAME = 'name';
	public const PROPERTY_PASSWORD = 'password';
	public const PROPERTY_READ_ONLY = 'readOnly';
	public const PROPERTY_RECORDING_CONSENT = 'recordingConsent';
	public const PROPERTY_SIP_ENABLED = 'sipEnabled';
	public const PROPERTY_TYPE = 'type';

	/**
	 * @param self::PROPERTY_* $property
	 */
	public function __construct(
		Room $room,
		protected string $property,
		protected \DateTime|string|int|null $newValue,
		protected \DateTime|string|int|null $oldValue = null,
		protected ?Participant $actor = null,
	) {
		parent::__construct($room);
	}

	public function getProperty(): string {
		return $this->property;
	}

	public function getNewValue(): \DateTime|string|int|null {
		return $this->newValue;
	}

	public function getOldValue(): \DateTime|string|int|null {
		return $this->oldValue;
	}

	public function getActor(): ?Participant {
		return $this->actor;
	}
}

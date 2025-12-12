<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\RecordingService;
use OCA\Talk\Webinary;
use OCP\IL10N;

readonly class Webinar extends APreset {
	public function __construct(
		protected IL10N $l,
	) {
	}

	#[\Override]
	public function getIdentifier(): string {
		return 'webinar';
	}

	#[\Override]
	public function getName(): string {
		return $this->l->t('Webinar');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l->t('Webinar');
	}

	#[\Override]
	public function getParameters(): array {
		return [
			Parameter::LOBBY_STATE->value => Webinary::LOBBY_NON_MODERATORS,
			Parameter::MENTION_PERMISSIONS->value => Room::MENTION_PERMISSIONS_MODERATORS,
			Parameter::PERMISSIONS->value => Attendee::PERMISSIONS_CUSTOM
				| Attendee::PERMISSIONS_CALL_JOIN
				| Attendee::PERMISSIONS_CHAT
				| Attendee::PERMISSIONS_REACT,
			Parameter::RECORDING_CONSENT->value => RecordingService::CONSENT_REQUIRED_YES,
			Parameter::ROOM_TYPE->value => Room::TYPE_PUBLIC,
		];
	}
}

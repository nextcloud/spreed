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
use OCP\IL10N;

readonly class Presentation extends APreset {
	public function __construct(
		protected IL10N $l,
	) {
	}

	#[\Override]
	public static function getIdentifier(): string {
		return 'presentation';
	}

	#[\Override]
	public function getName(): string {
		return $this->l->t('Presentation');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l->t('Presentation');
	}

	#[\Override]
	public function getParameters(): array {
		return [
			Parameter::MENTION_PERMISSIONS->value => Room::MENTION_PERMISSIONS_MODERATORS,
			Parameter::PERMISSIONS->value => Attendee::PERMISSIONS_CUSTOM
				| Attendee::PERMISSIONS_CALL_JOIN
				| Attendee::PERMISSIONS_CHAT
				| Attendee::PERMISSIONS_REACT,
			Parameter::RECORDING_CONSENT->value => RecordingService::CONSENT_REQUIRED_YES,
		];
	}
}

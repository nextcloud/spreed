<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCP\IL10N;

readonly class Team extends APreset {
	public function __construct(
		protected IL10N $l,
	) {
	}

	#[\Override]
	public static function getIdentifier(): string {
		return 'team';
	}

	#[\Override]
	public function getName(): string {
		// TRANSLATORS Conversation type - a room created for a team (formerly circle) and reflecting user changes
		return $this->l->t('Team');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l->t('Team');
	}

	#[\Override]
	public function getParameters(): array {
		return [
			// Default values during creating,
			// right after creation settings will be mapped to the actual Team settings
			Parameter::LISTABLE->value => Room::LISTABLE_USERS,
			Parameter::PERMISSIONS->value => Attendee::PERMISSIONS_DEFAULT,
		];
	}
}

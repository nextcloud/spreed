<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

use OCA\Talk\Room;
use OCP\IL10N;

readonly class Hallway implements IPreset {
	public function __construct(
		protected IL10N $l,
	) {
	}

	public function getIdentifier(): string {
		return 'hallway';
	}

	public function getName(): string {
		return $this->l->t('Hallway');
	}

	public function getDescription(): string {
		return $this->l->t('Hallway');
	}

	#[\Override]
	public function getParameters(): array {
		return [
			// Users but no guest users (by default)
			Parameter::LISTABLE->value => Room::LISTABLE_USERS,
			// If you were not there, you were not there …
			Parameter::MESSAGE_EXPIRATION->value => 3600,
		];
	}

}

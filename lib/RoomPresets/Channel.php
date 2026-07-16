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

readonly class Channel extends APreset {
	public function __construct(
		protected IL10N $l,
	) {
	}

	#[\Override]
	public static function getIdentifier(): string {
		return 'channel';
	}

	#[\Override]
	public function getName(): string {
		return $this->l->t('Channel');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l->t('Broadcast conversation for a large audience, where only moderators can post and calls are disabled.');
	}

	#[\Override]
	public function getParameters(): array {
		return [
			// Searchable and joinable by regular users
			Parameter::LISTABLE->value => Room::LISTABLE_USERS,
			// Only moderators can post, everyone else can only react.
			// Individual participants can still be granted more permissions afterwards.
			Parameter::PERMISSIONS->value => Attendee::PERMISSIONS_CUSTOM
				| Attendee::PERMISSIONS_REACT,
		];
	}
}

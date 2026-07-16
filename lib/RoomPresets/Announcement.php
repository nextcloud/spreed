<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

use OCA\Talk\Room;

/**
 * An announcement is a channel with additional restrictions, so all constraints
 * of {@see Channel} apply unless they are explicitly overwritten here.
 */
readonly class Announcement extends Channel {
	#[\Override]
	public static function getIdentifier(): string {
		return 'announcement';
	}

	#[\Override]
	public function getName(): string {
		return $this->l->t('Announcement');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l->t('Conversation to inform a fixed audience, which can not be left.');
	}

	#[\Override]
	public function getParameters(): array {
		return array_merge(parent::getParameters(), [
			// Contrary to a channel, announcements are not openly joinable
			Parameter::LISTABLE->value => Room::LISTABLE_NONE,
		]);
	}
}

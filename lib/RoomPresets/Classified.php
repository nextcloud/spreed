<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

use OCA\Talk\Room;
use OCA\Talk\Webinary;
use OCP\IL10N;

readonly class Classified extends APreset {
	public function __construct(
		private IL10N $l,
	) {
	}

	#[\Override]
	public static function getIdentifier(): string {
		return 'classified';
	}

	#[\Override]
	public function getName(): string {
		return $this->l->t('Classified conversation');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l->t('Locked down conversation for confidential topics, with public access, guests, SIP and recordings disabled.');
	}

	#[\Override]
	public function getParameters(): array {
		return [
			// Never public: no public link and therefore no guest access
			Parameter::ROOM_TYPE->value => Room::TYPE_GROUP,
			// Not openly joinable
			Parameter::LISTABLE->value => Room::LISTABLE_NONE,
			// No SIP dial-in/out
			Parameter::SIP_ENABLED->value => Webinary::SIP_DISABLED,
		];
	}
}

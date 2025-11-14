<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Recording;

use OCA\Talk\Room;

final readonly class RecordingStartedRequest {
	public function __construct(
		/** @var non-empty-string */
		public string $token,
		/** @var Room::RECORDING_* */
		public int $status,
		/** @var array{type: string, id: string} */
		public array $actor,
	) {
	}
}

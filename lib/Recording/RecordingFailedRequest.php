<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Recording;

final readonly class RecordingFailedRequest {
	public function __construct(
		/** @var non-empty-string */
		public string $token,
	) {
	}
}

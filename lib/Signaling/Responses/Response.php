<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Signaling\Responses;

final class Response {
	public function __construct(
		/** @var non-empty-string */
		public string $type,
		/** @var DialOut|null */
		public ?DialOut $dialOut,
	) {
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Recording;

final readonly class RecordingRequest {
	public const TYPE_STARTED = 'started';
	public const TYPE_STOPPED = 'stopped';
	public const TYPE_FAILED = 'failed';

	public function __construct(
		/** @var self::TYPE_* */
		public string $type,
		public ?RecordingStartedRequest $started = null,
		public ?RecordingStoppedRequest $stopped = null,
		public ?RecordingFailedRequest $failed = null,
	) {
	}
}

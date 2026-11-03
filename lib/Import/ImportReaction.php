<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

final class ImportReaction {
	public function __construct(
		public readonly ImportActor $actor,
		public readonly string $emoji,
		public readonly \DateTimeImmutable $created,
	) {
	}
}

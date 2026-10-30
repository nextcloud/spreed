<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

use OCA\Talk\Model\Attendee;

/**
 * An author, reactor or member of an imported message: an account
 * ({@see Attendee::ACTOR_USERS}) or a placeholder for a person without one
 * ({@see Attendee::ACTOR_IMPORTED}) with the name the source showed
 */
final class ImportActor {
	/**
	 * @param Attendee::ACTOR_USERS|Attendee::ACTOR_IMPORTED|string $type validated by the importer
	 * @param string $id user ID, or the stable ID of the placeholder (at most 64 characters)
	 * @param string $displayName name of a placeholder; ignored for accounts
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $id,
		public readonly string $displayName = '',
	) {
	}
}

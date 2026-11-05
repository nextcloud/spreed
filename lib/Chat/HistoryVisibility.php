<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat;

use OCA\Talk\Participant;
use OCP\Comments\IComment;

/**
 * Hides messages from before an attendee's "history visible from" time
 * ({@see \OCA\Talk\Model\Attendee::getHistorySince()}). The queries filter
 * with the time directly; this is the check for single messages.
 */
final class HistoryVisibility {
	public static function isVisible(?Participant $participant, IComment $comment): bool {
		$since = $participant?->getHistorySince();
		if ($since === null) {
			return true;
		}
		return $comment->getCreationDateTime()->getTimestamp() >= $since->getTimestamp();
	}

	public static function isVisibleAt(?Participant $participant, int $timestamp): bool {
		$since = $participant?->getHistorySince();
		return $since === null || $timestamp >= $since->getTimestamp();
	}
}

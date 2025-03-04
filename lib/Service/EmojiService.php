<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCP\IEmojiHelper;

class EmojiService {

	public function __construct(
		protected IEmojiHelper $emojiHelper,
	) {
	}

	/**
	 * Get the first combined full emoji (including gender, skin tone, job, …)
	 *
	 * @param string $roomName
	 * @param int $length
	 * @return string
	 */
	public function getFirstCombinedEmoji(string $roomName, int $length = 0): string {
		if (!$this->emojiHelper->doesPlatformSupportEmoji() || mb_strlen($roomName) === $length) {
			return '';
		}

		$attempt = mb_substr($roomName, 0, $length + 1);
		if ($this->emojiHelper->isValidSingleEmoji($attempt)) {
			$longerAttempt = $this->getFirstCombinedEmoji($roomName, $length + 1);
			return $longerAttempt ?: $attempt;
		}
		return '';
	}

	public function isValidSingleEmoji(string $string): bool {
		return $this->emojiHelper->doesPlatformSupportEmoji() && $this->emojiHelper->isValidSingleEmoji(mb_substr($string, 0, 1));
	}
}

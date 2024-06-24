<?php
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\Comments {
	class Comment implements \OCP\Comments\IComment {
		public function __construct(?array $data = null) {
		}
		public function setMessage($message, $maxLength = self::MAX_MESSAGE_LENGTH) {
		}
	}
}

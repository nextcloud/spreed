<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Talk\Exceptions;

/**
 * Thrown when a room is a proxy room and a message is trying to be posted.
 */
class MessagingNotAllowedException extends \OutOfBoundsException {
	public function __construct(?\Throwable $previous = null) {
		parent::__construct('Messaging is not allowed in proxy rooms', 1, $previous);
	}
}

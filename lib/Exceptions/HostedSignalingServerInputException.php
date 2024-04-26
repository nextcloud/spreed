<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Exceptions;

/**
 * Exception that is thrown when an API error happened. The message itself is already translated and can be handed out to the user.
 *
 * This exception should be used for the code flow and not for logging.
 *
 * This exception indicates  user solvable issues - like an already existing account or invalid input.
 */
class HostedSignalingServerInputException extends \Exception {
}

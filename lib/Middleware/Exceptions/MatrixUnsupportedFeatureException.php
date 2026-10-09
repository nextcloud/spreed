<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Middleware\Exceptions;

class MatrixUnsupportedFeatureException extends \Exception {
	public function __construct(
	) {
		parent::__construct('Feature is unsupported for Matrix conversations');
	}
}

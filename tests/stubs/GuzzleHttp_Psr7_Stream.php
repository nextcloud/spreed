<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

class Stream implements StreamInterface {
	/**
	 * @param resource $stream
	 * @param array{size?: int, metadata?: array<string, mixed>} $options
	 */
	public function __construct(
		$stream,
		array $options = [],
	) {
	}
}

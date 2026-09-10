<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Model;

/** A parsed `mxc://server/mediaId` content URI. */
final class Mxc {
	public function __construct(
		public readonly string $serverName,
		public readonly string $mediaId,
	) {
	}

	public static function parse(string $uri): self {
		if (!preg_match('~^mxc://([^/\s]+)/([^/\s?#]+)$~', $uri, $m)) {
			throw new \InvalidArgumentException('Invalid mxc URI: ' . $uri);
		}
		return new self($m[1], $m[2]);
	}

	public static function isValid(string $uri): bool {
		try {
			self::parse($uri);
			return true;
		} catch (\InvalidArgumentException) {
			return false;
		}
	}

	public function __toString(): string {
		return 'mxc://' . $this->serverName . '/' . $this->mediaId;
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

/**
 * Answer for one entry of a write: a message, a participant or a removal
 */
final class ImportResult {
	public const STATUS_OK = 'ok';
	public const STATUS_CONFLICT = 'conflict';
	public const STATUS_ERROR = 'error';

	/**
	 * @param self::STATUS_* $status
	 * @param int $id message ID (messages, removals) or attendee ID (participants)
	 * @param string $sha256 canonical hash of the message as stored
	 * @param ''|ImportException::CODE_* $errorCode set for conflicts and errors
	 */
	public function __construct(
		public readonly string $ref,
		public readonly string $status,
		public readonly int $id = 0,
		public readonly string $sha256 = '',
		public readonly string $errorCode = '',
		public readonly string $errorMessage = '',
	) {
	}

	public static function ok(string $ref, int $id, string $sha256 = ''): self {
		return new self($ref, self::STATUS_OK, $id, $sha256);
	}

	/**
	 * @param ImportException::CODE_* $code
	 */
	public static function conflict(string $ref, string $code, string $message, int $id = 0, string $sha256 = ''): self {
		return new self($ref, self::STATUS_CONFLICT, $id, $sha256, $code, $message);
	}

	public static function fromException(string $ref, ImportException $e): self {
		return new self($ref, self::STATUS_ERROR, 0, '', $e->getImportCode(), $e->getMessage());
	}
}

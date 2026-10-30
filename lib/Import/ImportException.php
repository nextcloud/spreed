<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

/**
 * An import request or entry Talk refuses. The code says why; the message
 * names the field and never contains message content.
 */
class ImportException extends \Exception {
	/** Input fails validation */
	public const CODE_INVALID = 'invalid';
	/** The conversation was not created by this importing app */
	public const CODE_NOT_IMPORTED = 'not_imported';
	/** Something with the same key exists that the import may not change */
	public const CODE_EXISTS = 'exists';
	/** Edited or deleted in Talk since the import wrote it */
	public const CODE_CHANGED = 'changed';
	/** The parent ref of a reply was not imported into the conversation */
	public const CODE_PARENT_MISSING = 'parent_missing';
	/** A user ID that is not an account */
	public const CODE_UNKNOWN_USER = 'unknown_user';
	/** A file ID that does not exist or has no owner who can share it */
	public const CODE_FILE_NOT_FOUND = 'file_not_found';
	/** The text is longer than a chat message may be */
	public const CODE_TOO_LONG = 'too_long';
	/** A reaction that is not a single emoji */
	public const CODE_INVALID_REACTION = 'invalid_reaction';

	/**
	 * @param self::CODE_* $importCode
	 */
	public function __construct(
		private readonly string $importCode,
		string $message,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, 0, $previous);
	}

	/**
	 * @return self::CODE_*
	 */
	public function getImportCode(): string {
		return $this->importCode;
	}
}

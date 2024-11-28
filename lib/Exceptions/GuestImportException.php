<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Talk\Exceptions;

class GuestImportException extends \Exception {
	public const REASON_ROOM = 'room';
	public const REASON_ROWS = 'rows';
	public const REASON_HEADER_EMAIL = 'header-email';
	public const REASON_HEADER_NAME = 'header-name';

	/**
	 * @param self::REASON_* $reason
	 * @param list<non-negative-int>|null $invalidLines
	 * @param non-negative-int|null $invites
	 * @param non-negative-int|null $duplicates
	 */
	public function __construct(
		protected readonly string $reason,
		protected readonly ?string $errorMessage = null,
		protected readonly ?array $invalidLines = null,
		protected readonly ?int $invites = null,
		protected readonly ?int $duplicates = null,
	) {
		parent::__construct($reason);
	}

	/**
	 * @return self::REASON_*
	 */
	public function getReason(): string {
		return $this->reason;
	}

	public function getErrorMessage(): ?string {
		return $this->errorMessage;
	}

	/**
	 * @return non-negative-int|null
	 */
	public function getInvites(): ?int {
		return $this->invites;
	}

	/**
	 * @return non-negative-int|null
	 */
	public function getDuplicates(): ?int {
		return $this->duplicates;
	}

	/**
	 * @return non-negative-int|null
	 */
	public function getInvalid(): ?int {
		return $this->invalidLines === null ? null : count($this->invalidLines);
	}

	/**
	 * @return list<non-negative-int>|null
	 */
	public function getInvalidLines(): ?array {
		return $this->invalidLines;
	}

	public function getData(): array {
		$data = ['error' => $this->errorMessage];
		if ($this->errorMessage !== null) {
			$data['message'] = $this->errorMessage;
		}
		if ($this->invites !== null) {
			$data['invites'] = $this->invites;
		}
		if ($this->duplicates !== null) {
			$data['duplicates'] = $this->duplicates;
		}
		if ($this->invalidLines !== null) {
			$data['invalid'] = count($this->invalidLines);
			$data['invalidLines'] = $this->invalidLines;
		}

		return $data;
	}
}

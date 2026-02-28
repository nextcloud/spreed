<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

abstract readonly class APreset {
	abstract public static function getIdentifier(): string;

	public function getName(): string {
		return self::getIdentifier();
	}

	public function getDescription(): string {
		return self::getIdentifier();
	}

	/**
	 * @return array<string, int>
	 */
	abstract public function getParameters(): array;

	/**
	 * @return array{identifier: string, name: string, description: string, parameters: array<string, int>}
	 */
	public function toArray(): array {
		return [
			'identifier' => self::getIdentifier(),
			'name' => $this->getName(),
			'description' => $this->getDescription(),
			'parameters' => $this->getParameters(),
		];
	}
}

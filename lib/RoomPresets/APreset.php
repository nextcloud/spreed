<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

abstract readonly class APreset implements IPreset {
	#[\Override]
	abstract public function getIdentifier(): string;

	#[\Override]
	public function getName(): string {
		return $this->getIdentifier();
	}

	#[\Override]
	public function getDescription(): string {
		return $this->getIdentifier();
	}

	/**
	 * @return array<string, int>
	 */
	#[\Override]
	abstract public function getParameters(): array;

	/**
	 * @return array{identifier: string, name: string, description: string, parameters: array<string, int>}
	 */
	#[\Override]
	public function toArray(): array {
		return [
			'identifier' => $this->getIdentifier(),
			'name' => $this->getName(),
			'description' => $this->getDescription(),
			'parameters' => $this->getParameters(),
		];
	}
}

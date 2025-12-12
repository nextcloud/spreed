<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

interface IPreset {
	public function getIdentifier(): string;

	public function getName(): string;

	public function getDescription(): string;

	/**
	 * @return array<string, int>
	 */
	public function getParameters(): array;

	/**
	 * @return array{identifier: string, name: string, description: string, parameters: array<string, int>}
	 */
	public function toArray(): array;
}

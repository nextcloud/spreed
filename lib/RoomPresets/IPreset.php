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
	 * @return array<Parameter, int>
	 */
	public function getParameters(): array;
}

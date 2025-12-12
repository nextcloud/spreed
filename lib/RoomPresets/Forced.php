<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

use OCP\AppFramework\Services\IAppConfig;

readonly class Forced implements IPreset {
	public const CONFIG_PREFIX_FORCE = 'force_';

	public function __construct(
		protected IAppConfig $appConfig,
	) {
	}

	public function getIdentifier(): string {
		return 'forced';
	}

	public function getName(): string {
		// Not shown in UI, so fall back to identifier
		return $this->getIdentifier();
	}

	public function getDescription(): string {
		// Not shown in UI, so fall back to identifier
		return $this->getIdentifier();
	}

	#[\Override]
	public function getParameters(): array {
		$forcedParameters = [];
		foreach (Parameter::cases() as $parameter) {
			$configName = self::getConfigNameForParameter($parameter->value);
			if ($this->appConfig->hasAppKey(self::CONFIG_PREFIX_FORCE . $configName)) {
				$forcedParameters[$parameter->value] = $this->appConfig->getAppValueInt(self::CONFIG_PREFIX_FORCE . $configName);
			}
		}
		return $forcedParameters;
	}

	protected static function getConfigNameForParameter(string $parameter): string {
		$parts = preg_split('/(?=[A-Z])/', $parameter);

		$configName = '';
		foreach ($parts as $part) {
			if ($configName === '') {
				$configName = $part;
			} else {
				$configName .= '_' . lcfirst($part);
			}
		}

		return $configName;
	}
}

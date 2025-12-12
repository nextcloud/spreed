<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\RoomPresets\DefaultPreset;
use OCA\Talk\RoomPresets\Hallway;
use OCA\Talk\RoomPresets\Parameter;
use OCA\Talk\RoomPresets\Presentation;
use OCA\Talk\RoomPresets\Preset;
use OCA\Talk\RoomPresets\Webinar;
use OCP\AppFramework\Services\IAppConfig;

class RoomPresetService {
	public const CONFIG_PREFIX_DEFAULT = 'default_';
	public const CONFIG_PREFIX_FORCE = 'force_';

	public function __construct(
		protected readonly IAppConfig $appConfig,
	) {
	}

	/**
	 * Order of priority:
	 * 1. App config `force_`
	 * 2. User provided value
	 * 3. Preset value
	 * 4. App config `default_`
	 * 5. Source code default
	 */
	public function getDefaultForPreset(Preset $preset, Parameter $parameter, ?int $provided): int {
		$configName = self::getConfigNameForParameter($parameter);
		if ($this->appConfig->hasAppKey(self::CONFIG_PREFIX_FORCE . $configName)) {
			return $this->appConfig->getAppValueInt(self::CONFIG_PREFIX_FORCE . $configName);
		}

		if ($provided !== null) {
			return $provided;
		}

		$value = match ($preset) {
			Preset::WEBINAR => Webinar::getDefault($parameter),
			Preset::PRESENTATION => Presentation::getDefault($parameter),
			Preset::HALLWAY => Hallway::getDefault($parameter),
			default => null,
		};

		if ($value !== null) {
			return $value;
		}

		if ($this->appConfig->hasAppKey(self::CONFIG_PREFIX_DEFAULT . $configName)) {
			return $this->appConfig->getAppValueInt(self::CONFIG_PREFIX_DEFAULT . $configName);
		}

		// Fall through to default preset
		return DefaultPreset::getDefault($parameter);
	}

	public static function getConfigNameForParameter(Parameter $parameter): string {
		$parts = preg_split('/(?=[A-Z])/', $parameter->value);

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

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

use OCP\AppFramework\Services\IAppConfig;

readonly class Forced extends APreset {
	public const CONFIG_PREFIX_FORCE = 'force_';

	public function __construct(
		protected IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function getIdentifier(): string {
		return 'forced';
	}

	#[\Override]
	public function getParameters(): array {
		$forcedParameters = [];
		foreach (Parameter::cases() as $parameter) {
			$forced = $this->getForcedParameter($parameter);
			if ($forced !== null) {
				$forcedParameters[$parameter->value] = $forced;
			}
		}
		return $forcedParameters;
	}

	public function forceParameter(Parameter $parameter, int $input): int {
		$forced = $this->getForcedParameter($parameter);
		return $forced ?? $input;
	}

	public function getForcedParameter(Parameter $parameter): ?int {
		$configName = self::getConfigNameForParameter($parameter);
		if ($configName !== null && $this->appConfig->hasAppKey($configName)) {
			return $this->appConfig->getAppValueInt($configName);
		}

		return null;
	}

	protected static function getConfigNameForParameter(Parameter $parameter): ?string {
		return match ($parameter) {
			Parameter::MENTION_PERMISSIONS => self::CONFIG_PREFIX_FORCE . 'mention_permissions',
			Parameter::PERMISSIONS => self::CONFIG_PREFIX_FORCE . 'permissions',
			Parameter::SIP_ENABLED => self::CONFIG_PREFIX_FORCE . 'sip_enabled',
			Parameter::LISTABLE => self::CONFIG_PREFIX_FORCE . 'listable',
			Parameter::MESSAGE_EXPIRATION => self::CONFIG_PREFIX_FORCE . 'message_expiration',
			default => null,
		};
	}
}

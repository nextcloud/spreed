<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\RecordingService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Services\IAppConfig;

readonly class DefaultPreset extends APreset {
	public const CONFIG_PREFIX_DEFAULT = 'default_';

	public function __construct(
		protected IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function getIdentifier(): string {
		return 'default';
	}

	#[\Override]
	public function getParameters(): array {
		$defaultParameters = [
			Parameter::ROOM_TYPE->value => Room::TYPE_GROUP,
			Parameter::READ_ONLY->value => Room::READ_WRITE,
			Parameter::LISTABLE->value => Room::LISTABLE_NONE,
			Parameter::MESSAGE_EXPIRATION->value => 0,
			Parameter::LOBBY_STATE->value => Webinary::LOBBY_NONE,
			Parameter::SIP_ENABLED->value => Webinary::SIP_DISABLED,
			Parameter::PERMISSIONS->value => Attendee::PERMISSIONS_DEFAULT,
			Parameter::RECORDING_CONSENT->value => RecordingService::CONSENT_REQUIRED_NO,
			Parameter::MENTION_PERMISSIONS->value => Room::MENTION_PERMISSIONS_EVERYONE,
		];

		foreach ($defaultParameters as $parameter => $defaultValue) {
			$configName = self::getConfigNameForParameter($parameter);
			if ($this->appConfig->hasAppKey(self::CONFIG_PREFIX_DEFAULT . $configName)) {
				$defaultParameters[$parameter] = $this->appConfig->getAppValueInt(self::CONFIG_PREFIX_DEFAULT . $configName);
			}
		}

		return $defaultParameters;
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

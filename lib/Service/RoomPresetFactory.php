<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Room;
use OCA\Talk\RoomPresets\APreset;
use OCA\Talk\RoomPresets\DefaultPreset;
use OCA\Talk\RoomPresets\Forced;
use OCA\Talk\RoomPresets\Presentation;
use OCA\Talk\RoomPresets\VoiceRoom;
use OCA\Talk\RoomPresets\Webinar;
use OCP\AppFramework\Services\IAppConfig;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

class RoomPresetFactory {
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @return array<string, APreset>
	 */
	public function getPresets(): array {
		$presetClasses = [
			DefaultPreset::class,
			Forced::class,
			Webinar::class,
			Presentation::class,
		];

		if ($this->appConfig->getAppValueInt('start_calls', Room::START_CALL_EVERYONE) !== Room::START_CALL_NOONE) {
			$presetClasses[] = VoiceRoom::class;
		}

		/** @var array<string, APreset> $presets */
		$presets = [];
		foreach ($presetClasses as $presetClass) {
			try {
				/** @var APreset $preset */
				$preset = \OCP\Server::get($presetClass);
			} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
				$this->logger->error('Could not load preset ' . $presetClass, ['exception' => $e]);
			}

			if (isset($presets[$preset::getIdentifier()])) {
				$this->logger->error('Duplicate preset identifier ' . $preset::getIdentifier() . ' from ' . $presetClass . ' and ' . $presets[$preset::getIdentifier()]::class);
				continue;
			}

			$presets[$preset::getIdentifier()] = $preset;
		}

		return $presets;
	}
}

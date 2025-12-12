<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\RoomPresets\DefaultPreset;
use OCA\Talk\RoomPresets\Forced;
use OCA\Talk\RoomPresets\Hallway;
use OCA\Talk\RoomPresets\IPreset;
use OCA\Talk\RoomPresets\Presentation;
use OCA\Talk\RoomPresets\Webinar;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

class RoomPresetFactory {
	public function __construct(
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * @return array<string, IPreset>
	 */
	public function getPresets(): array {
		$presetClasses = [
			DefaultPreset::class,
			Forced::class,
			Webinar::class,
			Presentation::class,
			Hallway::class,
		];

		/** @var array<string, IPreset> $presets */
		$presets = [];
		foreach ($presetClasses as $presetClass) {
			try {
				/** @var IPreset $preset */
				$preset = \OCP\Server::get($presetClass);
			} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
				$this->logger->error('Could not load preset ' . $presetClass, ['exception' => $e]);
			}

			if (isset($presets[$preset->getIdentifier()])) {
				$this->logger->error('Duplicate preset identifier ' . $preset->getIdentifier() . ' from ' . $presetClass . ' and ' . get_class($presets[$preset->getIdentifier()]));
				continue;
			}

			$presets[$preset->getIdentifier()] = $preset;
		}

		return $presets;
	}
}

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\SetupCheck;

use OCA\Talk\Config;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class RecordingBackend implements ISetupCheck {
	public function __construct(
		protected readonly Config $talkConfig,
		protected readonly IL10N $l,
	) {
	}

	#[\Override]
	public function getCategory(): string {
		return 'talk';
	}

	#[\Override]
	public function getName(): string {
		$name = $this->l->t('Recording backend');
		if ($this->talkConfig->getSignalingMode() === Config::SIGNALING_INTERNAL) {
			return '[skip] ' . $name;
		}
		return $name;
	}

	#[\Override]
	public function run(): SetupResult {
		if ($this->talkConfig->getSignalingMode() === Config::SIGNALING_INTERNAL) {
			return SetupResult::success($this->l->t('Using the recording backend requires a High-performance backend.'));
		}
		if (empty($this->talkConfig->getRecordingServers())) {
			return SetupResult::info($this->l->t('No recording backend configured'));
		}
		return SetupResult::success();
	}
}

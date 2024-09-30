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

class SIPConfiguration implements ISetupCheck {
	public function __construct(
		readonly protected Config $talkConfig,
		readonly protected IL10N $l,
	) {
	}

	public function getCategory(): string {
		return 'talk';
	}

	public function getName(): string {
		return $this->l->t('SIP dial-in');
	}

	public function run(): SetupResult {
		if ($this->talkConfig->getSignalingMode() !== Config::SIGNALING_INTERNAL) {
			return SetupResult::success();
		}
		if ($this->talkConfig->getSIPSharedSecret() === '' && $this->talkConfig->getDialInInfo() === '') {
			return SetupResult::success();
		}
		return SetupResult::error($this->l->t('Using the SIP functionality requires a High-performance backend.'));
	}
}
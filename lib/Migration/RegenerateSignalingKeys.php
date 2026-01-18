<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use OCA\Talk\Config;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * In JWT 7.0.2 validation for the key length was added, and it revealed that
 * Talk used a too short private key. So when generating a signaling ticket fails,
 * we generate a new private and public key with more complex curve which fixes it.
 */
class RegenerateSignalingKeys implements IRepairStep {
	public function __construct(
		protected IAppConfig $appConfig,
		protected Config $talkConfig,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Regenerate signaling keys';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$alg = $this->talkConfig->getSignalingTokenAlgorithm();

		if ($alg === 'ES384') {
			try {
				$this->talkConfig->getSignalingTicket(2, null);
			} catch (\Exception $e) {
				$this->appConfig->setAppValue('signaling_token_privkey_' . strtolower($alg), '');
				$this->appConfig->setAppValue('signaling_token_pubkey_' . strtolower($alg), '');

				$this->talkConfig->getSignalingTokenPrivateKey($alg);
			}
		}
	}
}

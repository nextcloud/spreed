<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\AppAPI\PublicFunctions;
use OCA\Talk\Exceptions\LiveTranscriptionAppAPIException;
use OCP\App\IAppManager;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class LiveTranscriptionService {

	public function __construct(
		private IAppManager $appManager,
	) {
	}

	public function isLiveTranscriptionAppEnabled(): bool {
		try {
			$appApiPublicFunctions = $this->getAppApiPublicFunctions();
		} catch (LiveTranscriptionAppAPIException $e) {
			return false;
		}

		$exApp = $appApiPublicFunctions->getExApp('live_transcription');
		if ($exApp === null || !$exApp['enabled']) {
			return false;
		}

		return true;
	}

	/**
	 * @throws LiveTranscriptionAppAPIException if app_api is not enabled or the
	 *                                          public functions could not be
	 *                                          got.
	 */
	private function getAppApiPublicFunctions(): object {
		if (!$this->appManager->isEnabledForUser('app_api')) {
			throw new LiveTranscriptionAppAPIException('app-api');
		}

		try {
			$appApiPublicFunctions = Server::get(PublicFunctions::class);
		} catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
			throw new LiveTranscriptionAppAPIException('app-api-functions');
		}

		return $appApiPublicFunctions;
	}
}

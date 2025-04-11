<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;

require __DIR__ . '/../../vendor/autoload.php';

/**
 * Federation context.
 */
class FederationContext implements Context, SnippetAcceptingContext {
	private static string $phpFederatedServerPid = '';

	/**
	 * The server is started also after the scenarios to ensure that it is
	 * properly cleaned up if stopped.
	 */
	#[BeforeScenario]
	#[AfterScenario]
	public function startFederatedServer(): void {
		if (self::$phpFederatedServerPid !== '') {
			return;
		}

		$port = getenv('PORT_FED');
		$rootDir = getenv('NEXTCLOUD_HOST_ROOT_DIR');

		self::$phpFederatedServerPid = exec('php -S localhost:' . $port . ' -t ' . $rootDir . ' >/dev/null & echo $!');
	}

	#[When('/^remote server is stopped$/')]
	public function remoteServerIsStopped(): void {
		if (self::$phpFederatedServerPid === '') {
			return;
		}

		exec('kill ' . self::$phpFederatedServerPid);

		self::$phpFederatedServerPid = '';
	}
}

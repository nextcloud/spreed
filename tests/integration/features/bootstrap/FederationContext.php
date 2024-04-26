<?php
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;

require __DIR__ . '/../../vendor/autoload.php';

/**
 * Federation context.
 */
class FederationContext implements Context, SnippetAcceptingContext {
	private static string $phpFederatedServerPid = '';

	/**
	 * @BeforeScenario
	 * @AfterScenario
	 *
	 * The server is started also after the scenarios to ensure that it is
	 * properly cleaned up if stopped.
	 */
	public function startFederatedServer() {
		if (self::$phpFederatedServerPid !== '') {
			return;
		}

		$port = getenv('PORT_FED');
		$rootDir = getenv('NEXTCLOUD_ROOT_DIR');

		self::$phpFederatedServerPid = exec('php -S localhost:' . $port . ' -t ' . $rootDir . ' >/dev/null & echo $!');
	}

	/**
	 * @When /^remote server is stopped$/
	 */
	public function remoteServerIsStopped() {
		if (self::$phpFederatedServerPid === '') {
			return;
		}

		exec('kill ' . self::$phpFederatedServerPid);

		self::$phpFederatedServerPid = '';
	}
}

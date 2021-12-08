<?php
/**
 * @copyright Copyright (c) 2016 Sergio Bertolin <sbertolin@solidgear.es>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Sergio Bertolin <sbertolin@solidgear.es>
 * @author Sergio Bertolín <sbertolin@solidgear.es>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;

require __DIR__ . '/../../vendor/autoload.php';

/**
 * Federation context.
 */
class FederationContext implements Context, SnippetAcceptingContext {

	/** @var string */
	private $baseUrl = '';
	/** @var string */
	private $baseRemoteUrl = '';

	/** @var ResponseInterface */
	private $response = null;

	/** @var string */
	private $currentUser = '';

	/** @var string */
	private $regularUserPassword;

	/** @var \SimpleXMLElement */
	private $lastCreatedShareData = null;

	public function __construct(string $baseUrl, array $admin, string $regularUserPassword) {
		$this->baseUrl = $baseUrl;
		$this->adminUser = $admin;
		$this->regularUserPassword = $regularUserPassword;

		// in case of ci deployment we take the server url from the environment
		$testServerUrl = getenv('TEST_SERVER_URL');
		if ($testServerUrl !== false) {
			$this->baseUrl = $testServerUrl;
		}
		$testServerUrl = getenv('TEST_REMOTE_URL');
		if ($testServerUrl !== false) {
			$this->baseRemoteUrl = $testServerUrl;
		}
	}

	/** @var string */
	private static $phpFederatedServerPid = '';

	/** @var string */
	private $lastAcceptedRemoteShareId;

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

//	/**
//	 * @BeforeScenario
//	 */
//	public function cleanupRemoteStorages() {
//		// Ensure that dangling remote storages from previous tests will not
//		// interfere with the current scenario.
//		// The storages must be cleaned before each scenario; they can not be
//		// cleaned after each scenario, as this hook is executed before the hook
//		// that removes the users, so the shares would be still valid and thus
//		// the storages would not be dangling yet.
//		$this->runOcc(['sharing:cleanup-remote-storages']);
//	}

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

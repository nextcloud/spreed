<?php
/**
 * @author Joachim Bauch <mail@joachim-bauch.de>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Spreed\Controller;

use OCP\AppFramework\Controller;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;

class AppSettingsController extends Controller {

	/** @var IL10N */
	private $l10n;
	/** @var IConfig */
	private $config;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IL10N $l10n
	 * @param IConfig $config
	 */
	public function __construct($appName,
								IRequest $request,
								IL10N $l10n,
								IConfig $config) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
		$this->config = $config;
	}

	/**
	 * Configure the settings of the Spreed app.
	 *
	 * @param string $turn_server
	 * @param string $turn_server_secret
	 * @param string $turn_server_protocols
	 * @return array
	 */
	public function setSpreedSettings($turn_server, $turn_server_secret, $turn_server_protocols) {
		if ($turn_server_protocols !== '') {
			if (!in_array($turn_server_protocols, array('udp,tcp', 'tcp', 'udp'))) {
				return array('data' =>
					array('message' =>
						(string) $this->l10n->t('Invalid protocols specified.')
					),
					'status' => 'error'
				);
			}
		}

		$currentStunServer = $this->config->getAppValue('spreed', 'stun_server', '');
		if ( $currentStunServer !== $stun_server ) {
			$this->config->setAppValue('spreed', 'stun_server', $stun_server);
		}

		$currentTurnServer = $this->config->getAppValue('spreed', 'turn_server', '');
		if ( $currentTurnServer !== $turn_server ) {
			$this->config->setAppValue('spreed', 'turn_server', $turn_server);
		}

		$currentTurnServerSecret = $this->config->getAppValue('spreed', 'turn_server_secret', '');
		if ( $currentTurnServerSecret !== $turn_server_secret ) {
			$this->config->setAppValue('spreed', 'turn_server_secret', $turn_server_secret);
		}

		$currentTurnServerProtocols = $this->config->getAppValue('spreed', 'turn_server_protocols', '');
		if ( $currentTurnServerProtocols !== $turn_server_protocols ) {
			$this->config->setAppValue('spreed', 'turn_server_protocols', $turn_server_protocols);
		}

		return array('data' =>
			array('message' =>
				(string) $this->l10n->t('Saved')
			),
			'status' => 'success'
		);
	}

}

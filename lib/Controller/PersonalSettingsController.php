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

use OCA\Spreed\Util;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;

class PersonalSettingsController extends Controller {

	/** @var IL10N */
	private $l10n;
	/** @var IConfig */
	private $config;
	/**
	 * @var string
	 */
	private $userId;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IL10N $l10n
	 * @param IConfig $config
	 * @param string $userId
	 */
	public function __construct($appName,
								IRequest $request,
								IL10N $l10n,
								IConfig $config,
								$userId) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
		$this->config = $config;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return TemplateResponse
	 */
	public function displayPanel() {
		$settings = Util::getTurnSettings($this->config, $this->userId);
		return new TemplateResponse('spreed', 'settings-personal', [
			'turnSettings' => $settings,
		], '');
	}

	/**
	 * Configure the personal settings of the Spreed app. The TURN server must
	 * be passed in the form "turnserver:port", e.g. "turn.domain.invalid:1234".
	 *
	 * @NoAdminRequired
	 * @param string $turn_server
	 * @param string $turn_username
	 * @param string $turn_password
	 * @param string $turn_protocols
	 */
	public function setSpreedSettings($turn_server, $turn_username, $turn_password, $turn_protocols) {
		if (!$this->userId) {
			return array('data' =>
				array('message' =>
					(string) $this->l10n->t('Not logged in.')
				),
				'status' => 'error'
			);
		}

		$turn_server = trim($turn_server);
		if ($turn_server !== "") {
			$parts = explode(":", $turn_server);
			if (count($parts) > 2) {
				return array('data' =>
					array('message' =>
						(string) $this->l10n->t('Invalid format, must be turnserver:port.')
					),
					'status' => 'error'
				);
			}

			$options = array(
				'options' => array(
					'default' => 0,
					'max_range' => 65535,
					'min_range' => 1,
				),
			);
			if (count($parts) === 2 && !filter_var($parts[1], FILTER_VALIDATE_INT, $options)) {
				return array('data' =>
					array('message' =>
						(string) $this->l10n->t('Invalid port specified.')
					),
					'status' => 'error'
				);
			}
		}

		if (empty($turn_server) || empty($turn_username) || empty($turn_password)) {
			return array('data' =>
				array('message' =>
					(string) $this->l10n->t('All fields have to be filled out.')
				),
				'status' => 'error'
			);
		}

		$turn_settings = array(
			'server' => $turn_server,
			'username' => $turn_username,
			'password' => $turn_password,
			'protocols' => $turn_protocols
		);

		$this->config->setUserValue($this->userId,
			'spreed',
			'turn_settings',
			json_encode($turn_settings));
		return array('data' =>
			array('message' =>
				(string) $this->l10n->t('Saved')
			),
			'status' => 'success'
		);
	}

}

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

namespace OCA\Spreed\Settings\Admin;


use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class TurnServer implements ISettings {

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$parameters = [
			'turnServer' => $this->config->getAppValue('spreed', 'turn_servers'),
		];

		return new TemplateResponse('spreed', 'settings/admin/turn-server', $parameters, '');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection() {
		return 'talk';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority() {
		return 70;
	}

}

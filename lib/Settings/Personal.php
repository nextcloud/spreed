<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Personal implements ISettings {

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * @return TemplateResponse returns the instance with all parameters set, ready to be rendered
	 * @since 9.1
	 */
	public function getForm(): TemplateResponse {
		$parameters = [ 'clients' => $this->getClientLinks() ];
		return new TemplateResponse('spreed', 'settings/personal/clients', $parameters);
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 * @since 9.1
	 */
	public function getSection(): string {
		return 'sync-clients';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 * @since 9.1
	 */
	public function getPriority(): int {
		return 30;
	}

	/**
	 * returns an array containing links to the various clients
	 *
	 * @return array
	 */
	private function getClientLinks(): array {
		$clients = [
			'android' => $this->config->getSystemValue('talk_customclient_android', 'https://play.google.com/store/apps/details?id=com.nextcloud.talk2'),
			'ios' => $this->config->getSystemValue('talk_customclient_ios', 'https://geo.itunes.apple.com/us/app/nextcloud-talk/id1296825574')
		];
		return $clients;
	}
}

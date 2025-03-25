<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
	) {
	}

	/**
	 * @return TemplateResponse returns the instance with all parameters set, ready to be rendered
	 * @since 9.1
	 */
	#[\Override]
	public function getForm(): TemplateResponse {
		$parameters = [ 'clients' => $this->getClientLinks() ];
		return new TemplateResponse('spreed', 'settings/personal/clients', $parameters);
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 * @since 9.1
	 */
	#[\Override]
	public function getSection(): string {
		return 'sync-clients';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 *             the admin section. The forms are arranged in ascending order of the
	 *             priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 * @since 9.1
	 */
	#[\Override]
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

<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Settings\Admin;


use OCA\Talk\Config;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\Settings\ISettings;

class GeneralSettings implements ISettings {

	/** @var IConfig */
	private $config;
	/** @var IInitialStateService */
	private $initialStateService;

	public function __construct(IConfig $config,
								IInitialStateService $initialStateService) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$this->initialStateService->provideInitialState('talk', 'start_calls', (int) $this->config->getAppValue('spreed', 'start_calls', Room::START_CALL_EVERYONE));
		$this->initialStateService->provideInitialState('talk', 'default_group_notification', (int) $this->config->getAppValue('spreed', 'default_group_notification', Participant::NOTIFY_MENTION));
		$this->initialStateService->provideInitialState('talk', 'conversations_files', (int) $this->config->getAppValue('spreed', 'conversations_files', '1'));
		$this->initialStateService->provideInitialState('talk', 'conversations_files_public_shares', (int) $this->config->getAppValue('spreed', 'conversations_files_public_shares', '1'));
		return new TemplateResponse('spreed', 'settings/admin/general-settings', [], '');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection(): string {
		return 'talk';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority(): int {
		return 0;
	}

}

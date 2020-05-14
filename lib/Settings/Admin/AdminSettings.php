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
use OCA\Talk\Model\Command;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\CommandService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {

	/** @var Config */
	private $talkConfig;
	/** @var IConfig */
	private $serverConfig;
	/** @var CommandService */
	private $commandService;
	/** @var IInitialStateService */
	private $initialStateService;
	/** @var ICacheFactory */
	private $memcacheFactory;

	public function __construct(Config $talkConfig,
								IConfig $serverConfig,
								CommandService $commandService,
								IInitialStateService $initialStateService,
								ICacheFactory $memcacheFactory) {
		$this->talkConfig = $talkConfig;
		$this->serverConfig = $serverConfig;
		$this->commandService = $commandService;
		$this->initialStateService = $initialStateService;
		$this->memcacheFactory = $memcacheFactory;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$this->initGeneralSettings();
		$this->initAllowedGroups();
		$this->initCommands();
		$this->initStunServers();
		$this->initTurnServers();
		$this->initSignalingServers();

		return new TemplateResponse('spreed', 'settings/admin-settings', [], '');
	}

	protected function initGeneralSettings(): void {
		$this->initialStateService->provideInitialState('talk', 'default_group_notification', (int) $this->serverConfig->getAppValue('spreed', 'default_group_notification', Participant::NOTIFY_MENTION));
		$this->initialStateService->provideInitialState('talk', 'conversations_files', (int) $this->serverConfig->getAppValue('spreed', 'conversations_files', '1'));
		$this->initialStateService->provideInitialState('talk', 'conversations_files_public_shares', (int) $this->serverConfig->getAppValue('spreed', 'conversations_files_public_shares', '1'));
	}

	protected function initAllowedGroups(): void {
		$this->initialStateService->provideInitialState('talk', 'start_conversations', $this->talkConfig->getAllowedConversationsGroupIds());
		$this->initialStateService->provideInitialState('talk', 'start_calls', (int) $this->serverConfig->getAppValue('spreed', 'start_calls', Room::START_CALL_EVERYONE));
		$this->initialStateService->provideInitialState('talk', 'allowed_groups', $this->talkConfig->getAllowedTalkGroupIds());
	}

	protected function initCommands(): void {
		$commands = $this->commandService->findAll();

		$result = array_map(function (Command $command) {
			return $command->asArray();
		}, $commands);

		$this->initialStateService->provideInitialState('talk', 'commands', $result);
	}

	protected function initStunServers(): void {
		$this->initialStateService->provideInitialState('talk', 'stun_servers', $this->talkConfig->getStunServers());
		$this->initialStateService->provideInitialState('talk', 'has_internet_connection', $this->serverConfig->getSystemValueBool('has_internet_connection', true));
	}

	protected function initTurnServers(): void {
		$this->initialStateService->provideInitialState('talk', 'turn_servers', $this->talkConfig->getTurnServers());
	}

	protected function initSignalingServers(): void {
		$this->initialStateService->provideInitialState('talk', 'has_cache_configured', $this->memcacheFactory->isAvailable());
		$this->initialStateService->provideInitialState('talk', 'signaling_mode', $this->talkConfig->getSignalingMode(false));
		$this->initialStateService->provideInitialState('talk', 'signaling_servers', [
			'servers' => $this->talkConfig->getSignalingServers(),
			'secret' => $this->talkConfig->getSignalingSecret(),
			'hideWarning' => $this->talkConfig->getHideSignalingWarning(),
		]);
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

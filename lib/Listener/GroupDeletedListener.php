<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\IConfig;

class GroupDeletedListener implements IEventListener {

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	public function handle(Event $event): void {
		if (!($event instanceof GroupDeletedEvent)) {
			// Unrelated
			return;
		}

		$gid = $event->getGroup()->getGID();

		$this->removeGroupFromConfig('sip_bridge_groups', $gid);
		$this->removeGroupFromConfig('start_conversations', $gid);
		$this->removeGroupFromConfig('allowed_groups', $gid);
	}

	protected function removeGroupFromConfig(string $configKey, string $removeGroupId): void {

		$json = $this->config->getAppValue('spreed', $configKey, '[]');
		$array = json_decode($json, true);
		$gids = \is_array($array) ? $array : [];

		$gids = array_filter($gids, static function($gid) use ($removeGroupId) {
			return $gid !== $removeGroupId;
		});

		$this->config->setAppValue('spreed', $configKey, json_encode($gids));
	}
}

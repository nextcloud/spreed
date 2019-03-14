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

namespace OCA\Spreed\Chat\Changelog;


use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Manager as RoomManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IL10N;

class Manager {

	/** @var IConfig */
	protected $config;
	/** @var RoomManager */
	protected $roomManager;
	/** @var ChatManager */
	protected $chatManager;
	/** @var ITimeFactory */
	protected $timeFactory;
	/** @var IL10N */
	protected $l;

	public function __construct(IConfig $config,
								RoomManager $roomManager,
								ChatManager $chatManager,
								ITimeFactory $timeFactory,
								IL10N $l) {
		$this->config = $config;
		$this->roomManager = $roomManager;
		$this->chatManager = $chatManager;
		$this->timeFactory = $timeFactory;
		$this->l = $l;
	}

	public function getChangelogForUser(string $userId): int {
		return (int) $this->config->getUserValue($userId, 'spreed', 'changelog', 0);
	}

	public function userHasNewChangelog(string $userId): bool {
		return $this->getChangelogForUser($userId) < count($this->getChangelogs());
	}

	public function updateChangelog(string $userId): void {
		$room = $this->roomManager->getChangelogRoom($userId);

		$logs = $this->getChangelogs();
		$hasReceivedLog = $this->getChangelogForUser($userId);

		foreach ($logs as $key => $changelog) {
			if ($key < $hasReceivedLog) {
				continue;
			}
			$this->chatManager->addChangelogMessage($room, $changelog);
		}

		$this->config->setUserValue($userId, 'spreed', 'changelog', count($this->getChangelogs()));
	}

	public function getChangelogs(): array {
		return [
			$this->l->t("Welcome to Nextcloud Talk!\nIn this conversation you will be informed about new features available in Nextcloud Talk."),
		];
	}
}

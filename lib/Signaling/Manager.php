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

namespace OCA\Talk\Signaling;


use OCA\Talk\Config;
use OCA\Talk\Room;
use OCP\ICache;
use OCP\ICacheFactory;

class Manager {

	/** @var Config */
	protected $talkConfig;
	/** @var ICache */
	private $cache;

	public function __construct(Config $talkConfig,
								ICacheFactory $cacheFactory) {
		$this->talkConfig = $talkConfig;
		$this->cache = $cacheFactory->createDistributed('hpb_servers');
	}

	public function getSignalingServerForConversation(?Room $room): string {
		switch ($this->talkConfig->getSignalingMode()) {
			case Config::SIGNALING_INTERNAL:
				return '';
			case Config::SIGNALING_EXTERNAL:
				return $this->getSignalingServerRandomly();
			case Config::SIGNALING_CLUSTER_CONVERSATION:
				if (!$room instanceof Room) {
					throw new \RuntimeException('Can not get conversation cluster HPB without conversation');
				}
				return $this->getSignalingServerConversationCluster($room);
			default:
				throw new \RuntimeException('Unknown signaling mode');
		}
	}

	public function getSignalingServerRandomly(): string {
		$servers = $this->talkConfig->getSignalingServers();
		try {
			$serverId = random_int(0, count($servers) - 1);
			return $servers[$serverId]['server'];
		} catch (\Exception $e) {
			return $servers[0]['server'];
		}
	}

	public function getSignalingServerConversationCluster(Room $room): string {
		$serverId = $room->getAssignedSignalingServer();
		$servers = $this->talkConfig->getSignalingServers();

		if ($serverId !== null && isset($servers[$serverId])) {
			return $servers[$serverId]['server'];
		}

		$randomServerId = random_int(0, count($servers) - 1);
		$serverId = $this->cache->get($room->getToken());
		if ($serverId === null) {
			$this->cache->set($room->getToken(), $randomServerId);
			$serverId = $randomServerId;
			$room->setAssignedSignalingServer($serverId);
		}

		return $servers[$serverId]['server'];
	}
}

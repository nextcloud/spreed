<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021, Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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

namespace OCA\Talk\Service;

use OCA\Talk\Model\BlockActor;
use OCA\Talk\Model\BlockActorMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\ICache;
use OCP\ICacheFactory;

class BlockActorService {

	/** @var BlockActorMapper */
	private $blockActorMapper;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var ICache */
	private $cache;

	public function __construct(BlockActorMapper $blockActorMapper,
								ITimeFactory $timeFactory,
								ICacheFactory $cacheFactory) {
		$this->blockActorMapper = $blockActorMapper;
		$this->timeFactory = $timeFactory;
		$this->cache = $cacheFactory->createDistributed('talk_blocked_users');
	}

	public function block(string $actorType, string $actorId, string $blockedType, string $blockedId) {
		$blockActor = new BlockActor();
		$blockActor->setActorType($actorType);
		$blockActor->setActorId($actorId);
		$blockActor->setBlockedType($blockedType);
		$blockActor->setBlockedId($blockedId);
		$blockActor->setDatetime($this->timeFactory->getDateTime());
		try {
			$this->blockActorMapper->insert($blockActor);
			$blockedList = $this->cache->get($actorId) ?? [];
			$blockedList[] = $blockActor;
			$this->cache->set($actorId, $blockedList);
		} catch (Exception $e) {
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
		}
	}

	public function unblock(string $actorType, string $actorId, string $blockedType, string $blockedId) {
		$blockActor = new BlockActor();
		$blockActor->setActorType($actorType);
		$blockActor->setActorId($actorId);
		$blockActor->setBlockedType($blockedType);
		$blockActor->setBlockedId($blockedId);
		$this->blockActorMapper->delete($blockActor);

		$blockedList = $this->cache->get($actorId);
		if (isset($blockedList[$blockedId])) {
			unset($blockedList[$blockedId]);
			$this->cache->set($actorId, $blockedList);
		}
	}

	public function listBlocked(string $actorId) {
		$blockedList = $this->cache->get($actorId);
		if (!$blockedList) {
			$blockedList = $this->blockActorMapper->getBlockListByBlocker($actorId);
			$this->cache->set($actorId, $blockedList);
		}
		return $blockedList;
	}

	public function user1BlockedUser2($user1, $user2) {
		$blockedList = $this->cache->get($user1);
		if (!$blockedList) {
			$blockedList = $this->blockActorMapper->getBlockListByBlocker($user1);
			$this->cache->set($user1, $blockedList);
		}
		return isset($blockedList[$user2]);
	}
}

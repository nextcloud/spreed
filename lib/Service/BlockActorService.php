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

use OCA\Talk\Model\BlockActorMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;

class BlockActorService {

	/** @var BlockActorMapper */
	private $blockActorMapper;
	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(BlockActorMapper $blockActorMapper,
								ITimeFactory $timeFactory) {
		$this->blockActorMapper = $blockActorMapper;
		$this->timeFactory = $timeFactory;
	}

	public function block(string $actorType, string $actorId, string $blockedType, string $blockedId): void {
		$blockActor = $this->blockActorMapper->createBlockActorFromRow([
			'actorType' => $actorType,
			'actorId' => $actorId,
			'blockedType' => $blockedType,
			'blockedId' => $blockedId
		]);
		$blockActor->setDatetime($this->timeFactory->getDateTime());
		try {
			$this->blockActorMapper->insert($blockActor);
		} catch (Exception $e) {
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
		}
	}

	public function unblock(string $actorType, string $actorId, string $blockedType, string $blockedId): void {
		$blockActor = $this->blockActorMapper->createBlockActorFromRow([
			'actorType' => $actorType,
			'actorId' => $actorId,
			'blockedType' => $blockedType,
			'blockedId' => $blockedId
		]);
		$this->blockActorMapper->delete($blockActor);
	}

	public function listBlocked(string $actorId): array {
		return $this->blockActorMapper->getBlockListByBlocker($actorId);
	}

	public function listBlockedByType(string $actorId, string $type): array {
		return $this->blockActorMapper->getBlockListByBlockerAndTypeOfBlocked($actorId, $type);
	}

	public function user1BlockedUser2($user1, $user2): bool {
		$blockedList = $this->listBlocked($user2);
		foreach ($blockedList as $list) {
			if (isset($list[$user2])) {
				return true;
			}
		}
		return false;
	}
}

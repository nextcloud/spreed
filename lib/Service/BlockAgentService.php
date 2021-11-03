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

use OCA\Talk\Model\BlockAgent;
use OCA\Talk\Model\BlockAgentMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;

class BlockAgentService {

	/** @var BlockAgentMapper */
	private $blockAgentMapper;
	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(BlockAgentMapper $blockAgentMapper,
								ITimeFactory $timeFactory) {
		$this->blockAgentMapper = $blockAgentMapper;
		$this->timeFactory = $timeFactory;
	}

	public function block(string $actorType, string $actorId, string $blockedType, string $blockedId) {
		$blockAgent = new BlockAgent();
		$blockAgent->setActorType($actorType);
		$blockAgent->setActorId($actorId);
		$blockAgent->setBlockedType($blockedType);
		$blockAgent->setBlockedId($blockedId);
		$blockAgent->setDatetime($this->timeFactory->getDateTime());
		try {
			$this->blockAgentMapper->insert($blockAgent);
		} catch (Exception $e) {
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
		}
	}

	public function unblock(string $actorType, string $actorId, string $blockedId) {
	}

	public function listBlocked(string $actorType, string $actorId, string $blockedId) {
	}
}

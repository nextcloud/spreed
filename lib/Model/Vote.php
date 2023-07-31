<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Kate Döen <kate.doeen@nextcloud.com>
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

namespace OCA\Talk\Model;

use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

/**
 * @method void setPollId(int $pollId)
 * @method int getPollId()
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method void setDisplayName(string $displayName)
 * @method string getDisplayName()
 * @method void setOptionId(int $optionId)
 * @method int getOptionId()
 *
 * @psalm-import-type SpreedPollVote from ResponseDefinitions
 */
class Vote extends Entity {
	protected int $pollId = 0;
	protected int $roomId = 0;
	protected string $actorType = '';
	protected string $actorId = '';
	protected ?string $displayName = null;
	protected ?int $optionId = null;

	public function __construct() {
		$this->addType('pollId', 'int');
		$this->addType('roomId', 'int');
		$this->addType('actorType', 'string');
		$this->addType('actorId', 'string');
		$this->addType('displayName', 'string');
		$this->addType('optionId', 'int');
	}

	/**
	 * @return SpreedPollVote
	 */
	public function asArray(): array {
		return [
			// The ids are not needed on the API level but only internally for optimising database queries
			// 'id' => $this->getId(),
			// 'pollId' => $this->getPollId(),
			// 'roomId' => $this->getRoomId(),
			'actorType' => $this->getActorType(),
			'actorId' => $this->getActorId(),
			'actorDisplayName' => $this->getDisplayName(),
			'optionId' => $this->getOptionId(),
		];
	}
}

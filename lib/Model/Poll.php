<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

use OCP\AppFramework\Db\Entity;

/**
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setQuestion(string $question)
 * @method string getQuestion()
 * @method void setOptions(string $options)
 * @method string getOptions()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method void setStatus(int $status)
 * @method int getStatus()
 * @method void setResultMode(int $resultMode)
 * @method int getResultMode()
 * @method void setMaxVotes(int $maxVotes)
 * @method int getMaxVotes()
 */
class Poll extends Entity {
	public const STATUS_OPEN = 0;
	public const STATUS_CLOSED = 1;
	public const MODE_PUBLIC = 0;
	public const MODE_HIDDEN = 1;
	public const MAX_VOTES_UNLIMITED = 0;

	protected int $roomId = 0;
	protected string $question = '';
	protected string $options = '';
	protected string $actorType = '';
	protected string $actorId = '';
	protected int $status = self::STATUS_OPEN;
	protected int $resultMode = self::MODE_PUBLIC;
	protected int $maxVotes = self::MAX_VOTES_UNLIMITED;

	public function __construct() {
		$this->addType('roomId', 'int');
		$this->addType('question', 'string');
		$this->addType('options', 'string');
		$this->addType('actorType', 'string');
		$this->addType('actorId', 'string');
		$this->addType('status', 'int');
		$this->addType('resultMode', 'int');
		$this->addType('maxVotes', 'int');
	}

	/**
	 * @return array
	 */
	public function asArray(): array {
		return [
			'id' => $this->getId(),
			'roomId' => $this->getRoomId(),
			'question' => $this->getQuestion(),
			'options' => $this->getOptions(),
			'actorType' => $this->getActorType(),
			'actorId' => $this->getActorId(),
			'status' => $this->getStatus(),
			'resultMode' => $this->getResultMode(),
			'maxVotes' => $this->getMaxVotes(),
		];
	}
}

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-method int<1, max> getId()
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setQuestion(string $question)
 * @method string getQuestion()
 * @psalm-method non-empty-string getQuestion()
 * @method void setOptions(string $options)
 * @method string getOptions()
 * @method void setVotes(string $votes)
 * @method string getVotes()
 * @method void setNumVoters(int $numVoters)
 * @method int getNumVoters()
 * @psalm-method int<0, max> getNumVoters()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @psalm-method Attendee::ACTOR_* getActorType()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @psalm-method non-empty-string getActorId()
 * @method void setDisplayName(string $displayName)
 * @method string getDisplayName()
 * @psalm-method non-empty-string getDisplayName()
 * @method void setStatus(int $status)
 * @method int getStatus()
 * @psalm-method self::STATUS_* getStatus()
 * @method void setResultMode(int $resultMode)
 * @method int getResultMode()
 * @psalm-method self::MODE_* getResultMode()
 * @method void setMaxVotes(int $maxVotes)
 * @method int getMaxVotes()
 * @psalm-method int<0, max> getMaxVotes()
 *
 * @psalm-import-type TalkPoll from ResponseDefinitions
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
	protected string $votes = '';
	protected int $numVoters = 0;
	protected string $actorType = '';
	protected string $actorId = '';
	protected ?string $displayName = null;
	protected int $status = self::STATUS_OPEN;
	protected int $resultMode = self::MODE_PUBLIC;
	protected int $maxVotes = self::MAX_VOTES_UNLIMITED;

	public function __construct() {
		$this->addType('roomId', 'int');
		$this->addType('question', 'string');
		$this->addType('options', 'string');
		$this->addType('votes', 'string');
		$this->addType('numVoters', 'int');
		$this->addType('actorType', 'string');
		$this->addType('actorId', 'string');
		$this->addType('displayName', 'string');
		$this->addType('status', 'int');
		$this->addType('resultMode', 'int');
		$this->addType('maxVotes', 'int');
	}

	/**
	 * @return TalkPoll
	 */
	public function asArray(): array {
		$votes = json_decode($this->getVotes(), true, 512, JSON_THROW_ON_ERROR);

		// Because PHP is turning arrays with sequent numeric keys "{"0":x,"1":y,"2":z}" into "[x,y,z]"
		// when json_encode() is used we have to prefix the keys with a string,
		// to prevent breaking in the mobile apps.
		/** @var array<string, int<0, max>> $prefixedVotes */
		$prefixedVotes = [];
		foreach ($votes as $option => $count) {
			$prefixedVotes['option-' . $option] = $count;
		}

		return [
			'id' => $this->getId(),
			// The room id is not needed on the API level but only internally for optimising database queries
			// 'roomId' => $this->getRoomId(),
			'question' => $this->getQuestion(),
			'options' => json_decode($this->getOptions(), true, 512, JSON_THROW_ON_ERROR),
			'votes' => $prefixedVotes,
			'numVoters' => $this->getNumVoters(),
			'actorType' => $this->getActorType(),
			'actorId' => $this->getActorId(),
			'actorDisplayName' => $this->getDisplayName(),
			'status' => $this->getStatus(),
			'resultMode' => $this->getResultMode(),
			'maxVotes' => $this->getMaxVotes(),
		];
	}
}

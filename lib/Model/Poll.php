<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\Exceptions\PollPropertyException;
use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @psalm-method int<1, max> getId()
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @psalm-method int<1, max> getRoomId()
 * @method string getQuestion()
 * @psalm-method non-empty-string getQuestion()
 * @method string getOptions()
 * @method void setVotes(string $votes)
 * @method string getVotes()
 * @method void setNumVoters(int $numVoters)
 * @method int getNumVoters()
 * @psalm-method int<0, max> getNumVoters()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @psalm-method TalkActorTypes getActorType()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @psalm-method non-empty-string getActorId()
 * @method void setDisplayName(string $displayName)
 * @method string getDisplayName()
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
 * @psalm-import-type TalkActorTypes from ResponseDefinitions
 * @psalm-import-type TalkPoll from ResponseDefinitions
 * @psalm-import-type TalkPollDraft from ResponseDefinitions
 */
class Poll extends Entity {
	public const STATUS_OPEN = 0;
	public const STATUS_CLOSED = 1;
	public const STATUS_DRAFT = 2;
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
		$this->addType('roomId', Types::BIGINT);
		$this->addType('question', Types::TEXT);
		$this->addType('options', Types::TEXT);
		$this->addType('votes', Types::TEXT);
		$this->addType('numVoters', Types::BIGINT);
		$this->addType('actorType', Types::STRING);
		$this->addType('actorId', Types::STRING);
		$this->addType('displayName', Types::STRING);
		$this->addType('status', Types::SMALLINT);
		$this->addType('resultMode', Types::SMALLINT);
		$this->addType('maxVotes', Types::INTEGER);
	}

	/**
	 * @return TalkPoll
	 */
	public function renderAsPoll(): array {
		$data = $this->renderAsDraft();
		$votes = json_decode($this->getVotes(), true, 512, JSON_THROW_ON_ERROR);

		// Because PHP is turning arrays with sequent numeric keys "{"0":x,"1":y,"2":z}" into "[x,y,z]"
		// when json_encode() is used we have to prefix the keys with a string,
		// to prevent breaking in the mobile apps.
		$data['votes'] = [];
		foreach ($votes as $option => $count) {
			$data['votes']['option-' . $option] = $count;
		}
		$data['numVoters'] = $this->getNumVoters();

		return $data;
	}

	/**
	 * @return TalkPollDraft
	 */
	public function renderAsDraft(): array {
		return [
			'id' => $this->getId(),
			// The room id is not needed on the API level but only internally for optimising database queries
			// 'roomId' => $this->getRoomId(),
			'question' => $this->getQuestion(),
			'options' => json_decode($this->getOptions(), true, 512, JSON_THROW_ON_ERROR),
			'actorType' => $this->getActorType(),
			'actorId' => $this->getActorId(),
			'actorDisplayName' => $this->getDisplayName(),
			'status' => $this->getStatus(),
			'resultMode' => $this->getResultMode(),
			'maxVotes' => $this->getMaxVotes(),
		];
	}

	public function isDraft(): bool {
		return $this->getStatus() === self::STATUS_DRAFT;
	}

	/**
	 * @param array $options
	 * @return void
	 * @throws PollPropertyException
	 */
	public function setOptions(array $options): void {
		try {
			$jsonOptions = json_encode($options, JSON_THROW_ON_ERROR, 1);
		} catch (\Exception) {
			throw new PollPropertyException(PollPropertyException::REASON_OPTIONS);
		}

		$validOptions = [];
		foreach ($options as $option) {
			if (!is_string($option)) {
				throw new PollPropertyException(PollPropertyException::REASON_OPTIONS);
			}

			$option = trim($option);
			if ($option !== '') {
				$validOptions[] = $option;
			}
		}

		if (count($validOptions) < 2) {
			throw new PollPropertyException(PollPropertyException::REASON_OPTIONS);
		}

		if (strlen($jsonOptions) > 60_000) {
			throw new PollPropertyException(PollPropertyException::REASON_OPTIONS);
		}

		$this->setter('options', [$jsonOptions]);
	}

	/**
	 * @param string $question
	 * @return void
	 * @throws PollPropertyException
	 */
	public function setQuestion(string $question): void {
		$question = trim($question);
		if ($question === '' || strlen($question) > 32_000) {
			throw new PollPropertyException(PollPropertyException::REASON_QUESTION);
		}

		$this->setter('question', [$question]);
	}
}

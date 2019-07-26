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

namespace OCA\Spreed\Model;


use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCP\Comments\IComment;
use OCP\IL10N;
use OCP\IUser;

class Message {

	/** @var Room */
	protected $room;

	/** @var IComment */
	protected $comment;

	/** @var IL10N */
	protected $l;

	/** @var Participant */
	protected $participant;

	/** @var bool */
	protected $visible = true;

	/** @var string */
	protected $type = '';

	/** @var string */
	protected $message = '';

	/** @var string */
	protected $rawMessage = '';

	/** @var array */
	protected $parameters = [];

	/** @var string */
	protected $actorType = '';

	/** @var string */
	protected $actorId = '';

	/** @var string */
	protected $actorDisplayName = '';

	public function __construct(Room $room,
								Participant $participant,
								IComment $comment,
								IL10N $l) {
		$this->room = $room;
		$this->participant = $participant;
		$this->comment = $comment;
		$this->l = $l;
	}

	/*
	 * Meta information
	 */

	public function getRoom(): Room {
		return $this->room;
	}

	public function getComment(): IComment {
		return $this->comment;
	}

	public function getL10n(): IL10N {
		return $this->l;
	}

	public function getParticipant(): Participant {
		return $this->participant;
	}

	/*
	 * Parsed message information
	 */

	public function setVisibility(bool $visible): void {
		$this->visible = $visible;
	}

	public function getVisibility(): bool {
		return $this->visible;
	}

	public function setMessage(string $message, array $parameters, string $rawMessage = ''): void {
		$this->message = $message;
		$this->parameters = $parameters;
		$this->rawMessage = $rawMessage;
	}

	public function getMessage(): string {
		return $this->message;
	}

	public function getMessageParameters(): array {
		return $this->parameters;
	}

	public function getMessageRaw(): string {
		return $this->rawMessage;
	}

	public function setMessageType(string $type): void {
		$this->type = $type;
	}

	public function getMessageType(): string {
		return $this->type;
	}

	public function setActor(string $type, string $id, string $displayName): void {
		$this->actorType = $type;
		$this->actorId = $id;
		$this->actorDisplayName = $displayName;
	}

	public function getActorType(): string {
		return $this->actorType;
	}

	public function getActorId(): string {
		return $this->actorId;
	}

	public function getActorDisplayName(): string {
		return $this->actorDisplayName;
	}
}

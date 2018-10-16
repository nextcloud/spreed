<?php
declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2018 Daniel Calviño Sánchez <danxuliu@gmail.com>
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

namespace OCA\Spreed\Search;

use OCP\Comments\IComment;
use OCP\IL10N;
use OCP\Search\Result as BaseResult;

class Result extends BaseResult {

	/** @var IL10N */
	private $l;

	/** @var string */
	public $type = 'chat-message';

	/** @var string */
	public $token;

	/** @var string */
	public $actorType;

	/** @var string */
	public $actorId;

	/** @var string */
	public $actorDisplayName;

	/** @var int */
	public $timestamp;

	/** @var string */
	public $relevantMessagePart;

	/**
	 * @param IL10N $l
	 * @param string $search
	 * @param IComment $comment
	 * @param string $token
	 * @param string $actorDisplayName
	 * @throws \InvalidArgumentException
	 */
	public function __construct(
			IL10N $l,
			string $search,
			IComment $comment,
			string $token,
			string $actorDisplayName
	) {
		parent::__construct(
			(int) $comment->getId(),
			$comment->getMessage()
		/* @todo , [link to chat message] */
		);

		$this->l = $l;

		$this->token = $token;

		$this->actorType = $comment->getActorType();
		$this->actorId = $comment->getActorId();
		$this->actorDisplayName = $actorDisplayName;

		$this->timestamp = $comment->getCreationDateTime()->getTimestamp();

		$this->relevantMessagePart = $this->getRelevantMessagePart($comment->getMessage(), $search);
	}

	/**
	 * @param string $message
	 * @param string $search
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	protected function getRelevantMessagePart(string $message, string $search): string {
		$start = stripos($message, $search);
		if ($start === false) {
			throw new InvalidArgumentException('Chat message section not found');
		}

		$end = $start + strlen($search);

		if ($start <= 25) {
			$start = 0;
			$prefix = '';
		} else {
			$start -= 25;
			$prefix = $this->l->t('…');
		}

		if ((strlen($message) - $end) <= 25) {
			$end = strlen($message);
			$suffix = '';
		} else {
			$end += 25;
			$suffix = $this->l->t('…');
		}

		return $prefix . substr($message, $start, $end - $start) . $suffix;
	}

}

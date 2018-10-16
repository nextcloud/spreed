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

use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\GuestManager;
use OCA\Spreed\Manager;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Search\Result as BaseResult;

class Provider extends \OCP\Search\Provider {

	/** @var IUserSession */
	private $userSession;

	/** @var IUserManager */
	private $userManager;

	/** @var ICommentsManager */
	private $commentsManager;

	/** @var IL10N */
	private $l;

	/** @var Manager */
	private $manager;

	/** @var GuestManager */
	private $guestManager;

	/**
	 * @param array $options as key => value
	 */
	public function __construct($options = array()) {
		parent::__construct($options);

		// Search providers are instantiated with "new $class($options)" in
		// "lib/private/Search.php", so the standard dependency injection system
		// can not be used and needs to be faked instead.
		$this->userSession = \OC::$server->getUserSession();
		$this->userManager = \OC::$server->getUserManager();
		$this->commentsManager = \OC::$server->getCommentsManager();
		$this->l = \OC::$server->getL10N('spreed');
		$this->manager = \OC::$server->resolve('\OCA\Spreed\Manager');
		// GuestManager uses IL10N, which is not registered in the server
		// container, so it needs to be explicitly constructed instead of
		// resolved.
		$this->guestManager = new GuestManager(
			\OC::$server->getDatabaseConnection(),
			\OC::$server->getMailer(),
			\OC::$server->query(\OCP\Defaults::class),
			\OC::$server->getUserSession(),
			\OC::$server->getURLGenerator(),
			\OC::$server->getL10N('spreed'),
			\OC::$server->getEventDispatcher()
		);
	}

	/**
	 * Search for $query
	 *
	 * @param string $query
	 * @return array An array of OCP\Search\Result's
	 */
	public function search($query): array {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return [];
		}

		$searchableRooms = $this->manager->getRoomsForParticipant($user->getUID());

		$results = [];
		$numComments = 50;
		$offset = 0;

		while (\count($results) < $numComments) {
			/** @var IComment[] $comments */
			$comments = $this->commentsManager->search($query, 'chat', '', '', $offset, $numComments);

			foreach ($comments as $comment) {
				$result = $this->getResultForComment($comment, $query, $searchableRooms);
				if ($result) {
					$results[] = $result;
				}
			}

			if (\count($comments) < $numComments) {
				// Didn't find more comments when we tried to get, so there are
				// no more comments.
				return $results;
			}

			$offset += $numComments;
			$numComments = 50 - \count($results);
		}

		return $results;
	}

	/**
	 * @param IComment $comment
	 * @param BaseResult[] $results
	 * @param string $query
	 * @param Room[] $searchableRooms
	 * @return BaseResult|null
	 */
	private function getResultForComment(IComment $comment, string $query, array $searchableRooms) {
		if ($comment->getActorType() !== 'users' && $comment->getActorType() !== 'guests') {
			return null;
		}

		// Ignore system messages
		if ($comment->getVerb() !== 'comment') {
			return null;
		}

		// Ignore rooms that the user is not a participant of
		$room = $this->getSearchableRoomById($searchableRooms, (int)$comment->getObjectId());
		if (!$room) {
			return null;
		}

		$actorDisplayName = '';
		if ($comment->getActorType() === 'users') {
			$user = $this->userManager->get($comment->getActorId());
			$actorDisplayName = $user instanceof IUser ? $user->getDisplayName() : '';
		} else if ($comment->getActorType() === 'guests') {
			try {
				$actorDisplayName = $this->guestManager->getNameBySessionHash($comment->getActorId());
			} catch (ParticipantNotFoundException $e) {
			}
		}

		try {
			return new Result(
				$this->l,
				$query,
				$comment,
				$room->getToken(),
				$actorDisplayName
			);
		} catch (\InvalidArgumentException $e) {
			return null;
		}
	}

	/**
	 * @param Room[] $searchableRooms
	 * @param int $roomId
	 * @return Room|null
	 */
	private function getSearchableRoomById(array $searchableRooms, int $roomId) {
		foreach ($searchableRooms as $room) {
			if ($room->getId() === $roomId) {
				return $room;
			}
		}

		return null;
	}
}

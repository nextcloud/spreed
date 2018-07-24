<?php
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Chat\SystemMessage;


use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\GuestManager;
use OCP\Comments\IComment;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

class Parser {

	/** @var IUserManager */
	protected $userManager;
	/** @var GuestManager */
	protected $guestManager;
	/** @var IL10N */
	protected $l;

	/** @var string[] */
	protected $displayNames = [];
	/** @var string[] */
	protected $guestNames = [];

	public function __construct(IUserManager $userManager, GuestManager $guestManager, IL10N $l) {
		$this->userManager = $userManager;
		$this->guestManager = $guestManager;
		$this->l = $l;
	}

	public function parseMessage(IComment $comment, string $displayName): array {
		$data = json_decode($comment->getMessage(), true);
		$message = $data['message'];
		$parameters = $data['parameters'];

		$parsedParameters = ['actor' => $this->getActor($comment)];
		$parsedMessage = $comment->getMessage();

		if ($message === 'conversation_created') {
			$parsedMessage = $this->l->t('{actor} created the conversation');
		} else if ($message === 'conversation_renamed') {
			$parsedMessage = $this->l->t('{actor} renamed the conversation from "%1$s" to "%2$s"', [$parameters['oldName'], $parameters['newName']]);
		} else if ($message === 'call_joined') {
			$parsedMessage = $this->l->t('{actor} joined the call');
		} else if ($message === 'call_left') {
			$parsedMessage = $this->l->t('{actor} left the call');
		} else if ($message === 'call_ended') {
			list($parsedMessage, $parsedParameters) = $this->parseCall($parameters);
		} else if ($message === 'guests_allowed') {
			$parsedMessage = $this->l->t('{actor} allowed guests in the conversation');
		} else if ($message === 'guests_disallowed') {
			$parsedMessage = $this->l->t('{actor} disallowed guests in the conversation');
		} else if ($message === 'password_set') {
			$parsedMessage = $this->l->t('{actor} set a password for the conversation');
		} else if ($message === 'password_removed') {
			$parsedMessage = $this->l->t('{actor} removed the password for the conversation');
		} else if ($message === 'user_added') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			$parsedMessage = $this->l->t('{actor} added {user} to the conversation');
		} else if ($message === 'user_removed') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			$parsedMessage = $this->l->t('{actor} removed {user} from the conversation');
		} else if ($message === 'moderator_promoted') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			$parsedMessage = $this->l->t('{actor} promoted {user} to moderator');
		} else if ($message === 'moderator_demoted') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			$parsedMessage = $this->l->t('{actor} demoted {user} from moderator');
		}

		$comment->setMessage($message);

		return [$parsedMessage, $parsedParameters];
	}

	protected function getActor(IComment $comment): array {
		if ($comment->getActorType() === 'guests') {
			return $this->getGuest($comment->getActorId());
		}

		return $this->getUser($comment->getActorId());
	}

	protected function getUser(string $uid): array {
		if (!isset($this->displayNames[$uid])) {
			$this->displayNames[$uid] = $this->getDisplayName($uid);
		}

		return [
			'type' => 'user',
			'id' => $uid,
			'name' => $this->displayNames[$uid],
		];
	}

	protected function getDisplayName(string $uid): string {
		$user = $this->userManager->get($uid);
		if ($user instanceof IUser) {
			return $user->getDisplayName();
		}
		return $uid;
	}

	protected function getGuest(string $sessionHash): array {
		if (!isset($this->guestNames[$sessionHash])) {
			$this->guestNames[$sessionHash] = $this->getGuestName($sessionHash);
		}

		return [
			'type' => 'guest',
			'id' => $sessionHash,
			'name' => $this->guestNames[$sessionHash],
		];
	}

	protected function getGuestName(string $sessionHash): string {
		try {
			return $this->l->t('%s (guest)', [$this->guestManager->getNameBySessionHash($sessionHash)]);
		} catch (ParticipantNotFoundException $e) {
			return $this->l->t('Guest');
		}
	}

	protected function parseCall(array $parameters): array {
		sort($parameters['users']);
		$numUsers = \count($parameters['users']);
		$displayedUsers = $numUsers;

		switch ($numUsers) {
			case 1:
				$subject = $this->l->t('Call with {user1} and {user2} (Duration {duration})');
				$subject = str_replace('{user2}', $this->l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				break;
			case 2:
				if ($parameters['guests'] === 0) {
					$subject = $this->l->t('Call with {user1} and {user2} (Duration {duration})');
				} else {
					$subject = $this->l->t('Call with {user1}, {user2} and {user3} (Duration {duration})');
					$subject = str_replace('{user3}', $this->l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 3:
				if ($parameters['guests'] === 0) {
					$subject = $this->l->t('Call with {user1}, {user2} and {user3} (Duration {duration})');
				} else {
					$subject = $this->l->t('Call with {user1}, {user2}, {user3} and {user4} (Duration {duration})');
					$subject = str_replace('{user4}', $this->l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 4:
				if ($parameters['guests'] === 0) {
					$subject = $this->l->t('Call with {user1}, {user2}, {user3} and {user4} (Duration {duration})');
				} else {
					$subject = $this->l->t('Call with {user1}, {user2}, {user3}, {user4} and {user5} (Duration {duration})');
					$subject = str_replace('{user5}', $this->l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 5:
			default:
				$subject = $this->l->t('Call with {user1}, {user2}, {user3}, {user4} and {user5} (Duration {duration})');
				if ($numUsers === 5 && $parameters['guests'] === 0) {
					$displayedUsers = 5;
				} else {
					$displayedUsers = 4;
					$numOthers = $parameters['guests'] + $numUsers - $displayedUsers;
					$subject = str_replace('{user5}', $this->l->n('%n other', '%n others', $numOthers), $subject);
				}
		}

		$params = [];
		for ($i = 1; $i <= $displayedUsers; $i++) {
			$params['user' . $i] = $this->getUser($parameters['users'][$i - 1]);
		}

		$subject = str_replace('{duration}', $this->getDuration($parameters['duration']), $subject);
		return [
			$subject,
			$params,
		];
	}

	protected function getDuration($seconds): string {
		$hours = floor($seconds / 3600);
		$seconds %= 3600;
		$minutes = floor($seconds / 60);
		$seconds %= 60;

		if ($hours > 0) {
			$duration = sprintf('%1$d:%2$02d:%3$02d', $hours, $minutes, $seconds);
		} else {
			$duration = sprintf('%1$d:%2$02d', $minutes, $seconds);
		}

		return $duration;
	}
}

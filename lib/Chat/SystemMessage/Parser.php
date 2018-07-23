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


use OCA\Spreed\Room;
use OCP\Comments\IComment;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

class Parser {

	/** @var IUserManager */
	protected $userManager;
	/** @var IL10N */
	protected $l;

	/** @var string[] */
	protected $displayNames = [];

	public function __construct(IUserManager $userManager, IL10N $l) {
		$this->userManager = $userManager;
		$this->l = $l;
	}

	public function parseMessage(IComment $comment, string $displayName): array {
		$data = json_decode($comment->getMessage(), true);
		$message = $data['message'];
		$parameters = $data['parameters'];

		$parsedParameters = ['actor' => $this->getActor($comment, $displayName)];
		$parsedMessage = $comment->getMessage();

		if ($message === 'joined_call') {
			$parsedMessage = $this->l->t('{actor} joined the call');
		} else if ($message === 'left_call') {
			$parsedMessage = $this->l->t('{actor} left the call');
		} else if ($message === 'created_conversation') {
			$parsedMessage = $this->l->t('{actor} created the conversation');
		} else if ($message === 'renamed_conversation') {
			$parsedMessage = $this->l->t('{actor} renamed the conversation from "%1$s" to "%2$s"', [$parameters['oldName'], $parameters['newName']]);
		} else if ($message === 'allowed_guests') {
			$parsedMessage = $this->l->t('{actor} allowed guests in the conversation');
		} else if ($message === 'disallowed_guests') {
			$parsedMessage = $this->l->t('{actor} disallowed guests in the conversation');
		} else if ($message === 'set_password') {
			$parsedMessage = $this->l->t('{actor} set a password for the conversation');
		} else if ($message === 'removed_password') {
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


		return [$parsedMessage, $parsedParameters];
	}

	protected function getActor(IComment $comment, string $displayName): array {
		return [
			'type' => 'user',
			'id' => $comment->getActorId(),
			'name' => $displayName,
		];
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
}

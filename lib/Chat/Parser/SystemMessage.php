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

namespace OCA\Spreed\Chat\Parser;


use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\GuestManager;
use OCA\Spreed\Share\RoomShareProvider;
use OCP\Comments\IComment;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

class SystemMessage {

	/** @var IUserManager */
	protected $userManager;
	/** @var GuestManager */
	protected $guestManager;
	/** @var IUserSession */
	protected $userSession;
	/** @var RoomShareProvider */
	protected $shareProvider;
	/** @var IRootFolder */
	protected $rootFolder;
	/** @var IURLGenerator */
	protected $url;
	/** @var IL10N */
	protected $l;

	/** @var null|IUser */
	protected $recipient;
	/** @var string[] */
	protected $displayNames = [];
	/** @var string[] */
	protected $guestNames = [];

	public function __construct(IUserManager $userManager,
								GuestManager $guestManager,
								IUserSession $userSession,
								RoomShareProvider $shareProvider,
								IRootFolder $rootFolder,
								IURLGenerator $url,
								IL10N $l) {
		$this->userManager = $userManager;
		$this->guestManager = $guestManager;
		$this->userSession = $userSession;
		$this->shareProvider = $shareProvider;
		$this->rootFolder = $rootFolder;
		$this->url = $url;
		$this->l = $l;
		$this->recipient = $this->userSession->getUser();
	}

	public function setUserInfo(IUser $user, IL10N $l) {
		$this->recipient = $user;
		$this->l = $l;
	}

	public function parseMessage(IComment $comment): array {
		$data = json_decode($comment->getMessage(), true);
		$message = $data['message'];
		$parameters = $data['parameters'];

		$parsedParameters = ['actor' => $this->getActor($comment)];
		$currentUserIsActor = $this->recipient instanceof IUser && $this->recipient->getUID() === $parsedParameters['actor']['id'];
		$parsedMessage = $comment->getMessage();

		if ($message === 'conversation_created') {
			$parsedMessage = $this->l->t('{actor} created the conversation');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You created the conversation');
			}
		} else if ($message === 'conversation_renamed') {
			$parsedMessage = $this->l->t('{actor} renamed the conversation from "%1$s" to "%2$s"', [$parameters['oldName'], $parameters['newName']]);
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You renamed the conversation from "%1$s" to "%2$s"', [$parameters['oldName'], $parameters['newName']]);
			}
		} else if ($message === 'call_started') {
			$parsedMessage = $this->l->t('{actor} started a call');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You started a call');
			}
		} else if ($message === 'call_joined') {
			$parsedMessage = $this->l->t('{actor} joined the call');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You joined the call');
			}
		} else if ($message === 'call_left') {
			$parsedMessage = $this->l->t('{actor} left the call');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You left the call');
			}
		} else if ($message === 'call_ended') {
			list($parsedMessage, $parsedParameters) = $this->parseCall($parameters);
		} else if ($message === 'guests_allowed') {
			$parsedMessage = $this->l->t('{actor} allowed guests');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You allowed guests');
			}
		} else if ($message === 'guests_disallowed') {
			$parsedMessage = $this->l->t('{actor} disallowed guests');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You disallowed guests');
			}
		} else if ($message === 'password_set') {
			$parsedMessage = $this->l->t('{actor} set a password');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You set a password');
			}
		} else if ($message === 'password_removed') {
			$parsedMessage = $this->l->t('{actor} removed the password');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You removed the password');
			}
		} else if ($message === 'user_added') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			$parsedMessage = $this->l->t('{actor} added {user}');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You added {user}');
			} else if ($this->recipient instanceof IUser && $this->recipient->getUID() === $parsedParameters['user']['id']) {
				$parsedMessage = $this->l->t('{actor} added you');
			}
		} else if ($message === 'user_removed') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			if ($parsedParameters['user']['id'] === $parsedParameters['actor']['id']) {
				$parsedMessage = $this->l->t('{actor} left the conversation');
			} else {
				$parsedMessage = $this->l->t('{actor} removed {user}');
				if ($currentUserIsActor) {
					$parsedMessage = $this->l->t('You removed {user}');
				} else if ($this->recipient instanceof IUser && $this->recipient->getUID() === $parsedParameters['user']['id']) {
					$parsedMessage = $this->l->t('{actor} removed you');
				}
			}
		} else if ($message === 'moderator_promoted') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			$parsedMessage = $this->l->t('{actor} promoted {user} to moderator');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You promoted {user} to moderator');
			} else if ($this->recipient instanceof IUser && $this->recipient->getUID() === $parsedParameters['user']['id']) {
				$parsedMessage = $this->l->t('{actor} promoted you to moderator');
			}
		} else if ($message === 'moderator_demoted') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			$parsedMessage = $this->l->t('{actor} demoted {user} from moderator');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You demoted {user} from moderator');
			} else if ($this->recipient instanceof IUser && $this->recipient->getUID() === $parsedParameters['user']['id']) {
				$parsedMessage = $this->l->t('{actor} demoted you from moderator');
			}
		} else if ($message === 'file_shared') {
			try {
				$parsedParameters['file'] = $this->getFileFromShare($parameters['share']);
				$parsedMessage = '{file}';
				$comment->setVerb('comment');
			} catch (\Exception $e) {
				$parsedMessage = $this->l->t('{actor} shared a file which is no longer available');
				if ($currentUserIsActor) {
					$parsedMessage = $this->l->t('You shared a file which is no longer available');
				}
			}
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

	/**
	 * @param string $shareId
	 * @return array
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Share\Exceptions\ShareNotFound
	 */
	protected function getFileFromShare(string $shareId): array {
		$share = $this->shareProvider->getShareById($shareId);
		$node = $share->getNode();
		$name = $node->getName();
		$path = $node->getName();

		if ($this->recipient instanceof IUser) {
			if ($this->userSession->getUser() === $this->recipient) {
				$userFolder = $this->rootFolder->getUserFolder($this->recipient->getUID());
				if ($userFolder instanceof Node) {
					$userNodes = $userFolder->getById($node->getId());
					/** @var Node $userNode */
					$userNode = reset($userNodes);
					$fullPath = $userNode->getPath();
					$pathSegments = explode('/', $fullPath, 4);
					$name = $userNode->getName();
					$path = $pathSegments[3] ?? $path;
				}
			} else {
				$fullPath = $node->getPath();
				$pathSegments = explode('/', $fullPath, 4);
				$name = $node->getName();
				$path = $pathSegments[3] ?? $path;
			}

			$url = $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', [
				'fileid' => $node->getId(),
			]);
		} else {
			$url = $this->url->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', [
				'token' => $share->getToken(),
			]);
		}

		return [
			'type' => 'file',
			'id' => $node->getId(),
			'name' => $name,
			'path' => $path,
			'link' => $url,
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

<?php
declare(strict_types=1);
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

namespace OCA\Talk\Chat\Parser;


use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\GuestManager;
use OCA\Talk\Model\Message;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Share\RoomShareProvider;
use OCP\Comments\IComment;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IPreview as IPreviewManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;

class SystemMessage {

	/** @var IUserManager */
	protected $userManager;
	/** @var GuestManager */
	protected $guestManager;
	/** @var IPreviewManager */
	protected $previewManager;
	/** @var RoomShareProvider */
	protected $shareProvider;
	/** @var IRootFolder */
	protected $rootFolder;
	/** @var IURLGenerator */
	protected $url;
	/** @var IL10N */
	protected $l;

	/** @var string[] */
	protected $displayNames = [];
	/** @var string[] */
	protected $guestNames = [];

	public function __construct(IUserManager $userManager,
								GuestManager $guestManager,
								IPreviewManager $previewManager,
								RoomShareProvider $shareProvider,
								IRootFolder $rootFolder,
								IURLGenerator $url) {
		$this->userManager = $userManager;
		$this->guestManager = $guestManager;
		$this->previewManager = $previewManager;
		$this->shareProvider = $shareProvider;
		$this->rootFolder = $rootFolder;
		$this->url = $url;
	}

	/**
	 * @param Message $chatMessage
	 * @throws \OutOfBoundsException
	 */
	public function parseMessage(Message $chatMessage): void {
		$this->l = $chatMessage->getL10n();
		$comment = $chatMessage->getComment();
		$data = json_decode($chatMessage->getMessage(), true);
		if (!\is_array($data)) {
			throw new \OutOfBoundsException('Invalid message');
		}

		$message = $data['message'];
		$parameters = $data['parameters'];
		$parsedParameters = ['actor' => $this->getActor($comment)];

		$participant = $chatMessage->getParticipant();
		if (!$participant->isGuest()) {
			$currentUserIsActor = $parsedParameters['actor']['type'] === 'user' &&
				$participant->getUser() === $parsedParameters['actor']['id'];
		} else {
			$currentUserIsActor = $parsedParameters['actor']['type'] === 'guest' &&
				sha1($participant->getSessionId()) === $parsedParameters['actor']['id'];
		}

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
			[$parsedMessage, $parsedParameters] = $this->parseCall($parameters);
		} else if ($message === 'read_only_off') {
			$parsedMessage = $this->l->t('{actor} unlocked the conversation');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You unlocked the conversation');
			}
		} else if ($message === 'read_only') {
			$parsedMessage = $this->l->t('{actor} locked the conversation');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You locked the conversation');
			}
		} else if ($message === 'lobby_timer_reached') {
			$parsedMessage = $this->l->t('The conversation is now open to everyone');
		} else if ($message === 'lobby_none') {
			$parsedMessage = $this->l->t('{actor} opened the conversation to everyone');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You opened the conversation to everyone');
			}
		} else if ($message === 'lobby_non_moderators') {
			$parsedMessage = $this->l->t('{actor} restricted the conversation to moderators');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You restricted the conversation to moderators');
			}
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
			if ($parsedParameters['user']['id'] === $parsedParameters['actor']['id']) {
				if ($currentUserIsActor) {
					$parsedMessage = $this->l->t('You joined the conversation');
				} else {
					$parsedMessage = $this->l->t('{actor} joined the conversation');
				}
			} else if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You added {user}');
			} else if (!$participant->isGuest() && $participant->getUser() === $parsedParameters['user']['id']) {
				$parsedMessage = $this->l->t('{actor} added you');
			}
		} else if ($message === 'user_removed') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			if ($parsedParameters['user']['id'] === $parsedParameters['actor']['id']) {
				if ($currentUserIsActor) {
					$parsedMessage = $this->l->t('You left the conversation');
				} else {
					$parsedMessage = $this->l->t('{actor} left the conversation');
				}
			} else {
				$parsedMessage = $this->l->t('{actor} removed {user}');
				if ($currentUserIsActor) {
					$parsedMessage = $this->l->t('You removed {user}');
				} else if (!$participant->isGuest() && $participant->getUser() === $parsedParameters['user']['id']) {
					$parsedMessage = $this->l->t('{actor} removed you');
				}
			}
		} else if ($message === 'moderator_promoted') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			$parsedMessage = $this->l->t('{actor} promoted {user} to moderator');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You promoted {user} to moderator');
			} else if (!$participant->isGuest() && $participant->getUser() === $parsedParameters['user']['id']) {
				$parsedMessage = $this->l->t('{actor} promoted you to moderator');
			}
		} else if ($message === 'moderator_demoted') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			$parsedMessage = $this->l->t('{actor} demoted {user} from moderator');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You demoted {user} from moderator');
			} else if (!$participant->isGuest() && $participant->getUser() === $parsedParameters['user']['id']) {
				$parsedMessage = $this->l->t('{actor} demoted you from moderator');
			}
		} else if ($message === 'guest_moderator_promoted') {
			$parsedParameters['user'] = $this->getGuest($parameters['session']);
			$parsedMessage = $this->l->t('{actor} promoted {user} to moderator');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You promoted {user} to moderator');
			} else if ($participant->isGuest() && $participant->getSessionId() === $parsedParameters['user']['id']) {
				$parsedMessage = $this->l->t('{actor} promoted you to moderator');
			}
		} else if ($message === 'guest_moderator_demoted') {
			$parsedParameters['user'] = $this->getGuest($parameters['session']);
			$parsedMessage = $this->l->t('{actor} demoted {user} from moderator');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You demoted {user} from moderator');
			} else if ($participant->isGuest() && $participant->getSessionId() === $parsedParameters['user']['id']) {
				$parsedMessage = $this->l->t('{actor} demoted you from moderator');
			}
		} else if ($message === 'file_shared') {
			try {
				$parsedParameters['file'] = $this->getFileFromShare($participant, $parameters['share']);
				$parsedMessage = '{file}';
				$chatMessage->setMessageType('comment');
			} catch (\Exception $e) {
				$parsedMessage = $this->l->t('{actor} shared a file which is no longer available');
				if ($currentUserIsActor) {
					$parsedMessage = $this->l->t('You shared a file which is no longer available');
				}
			}
		} else {
			throw new \OutOfBoundsException('Unknown subject');
		}

		$chatMessage->setMessage($parsedMessage, $parsedParameters, $message);
	}

	/**
	 * @param Participant $participant
	 * @param string $shareId
	 * @return array
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Share\Exceptions\ShareNotFound
	 */
	protected function getFileFromShare(Participant $participant, string $shareId): array {
		$share = $this->shareProvider->getShareById($shareId);
		$node = $share->getNode();
		$name = $node->getName();
		$path = $name;

		if (!$participant->isGuest()) {
			if ($share->getShareOwner() !== $participant->getUser()) {
				$userFolder = $this->rootFolder->getUserFolder($participant->getUser());
				if ($userFolder instanceof Node) {
					$userNodes = $userFolder->getById($node->getId());

					if (empty($userNodes)) {
						// FIXME This should be much more sensible, e.g.
						// 1. Only be executed on "Waiting for new messages"
						// 2. Once per request
						\OC_Util::tearDownFS();
						\OC_Util::setupFS($participant->getUser());
						$userNodes = $userFolder->getById($node->getId());
					}

					if (empty($userNodes)) {
						throw new NotFoundException('File was not found');
					}

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
			'id' => (string) $node->getId(),
			'name' => $name,
			'path' => $path,
			'link' => $url,
			'mimetype' => $node->getMimeType(),
			'preview-available' => $this->previewManager->isAvailable($node) ? 'yes' : 'no',
		];
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
			'id' => 'guest/' . $sessionHash,
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
			case 0:
				$subject = $this->l->n(
					'Call with %n guest (Duration {duration})',
					'Call with %n guests (Duration {duration})',
					$parameters['guests']
				);
				break;
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
		if ($displayedUsers > 0) {
			for ($i = 1; $i <= $displayedUsers; $i++) {
				$params['user' . $i] = $this->getUser($parameters['users'][$i - 1]);
			}
		}

		$subject = str_replace('{duration}', $this->getDuration($parameters['duration']), $subject);
		return [
			$subject,
			$params,
		];
	}

	protected function getDuration(int $seconds): string {
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

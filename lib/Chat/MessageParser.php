<?php
declare(strict_types=1);
/**
 *
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

namespace OCA\Spreed\Chat;

use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\GuestManager;
use OCA\Spreed\Model\Message;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCP\Comments\IComment;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Helper class to get a rich message from a plain text message.
 */
class MessageParser {

	/** @var EventDispatcherInterface */
	private $dispatcher;

	/** @var IUserManager */
	private $userManager;

	/** @var GuestManager */
	private $guestManager;

	/** @var array */
	protected $guestNames = [];

	public function __construct(EventDispatcherInterface $dispatcher,
								IUserManager $userManager,
								GuestManager $guestManager) {
		$this->dispatcher = $dispatcher;
		$this->userManager = $userManager;
		$this->guestManager = $guestManager;
	}

	public function createMessage(Room $room, Participant $participant, IComment $comment, IL10N $l): Message {
		return new Message($room, $participant, $comment, $l);
	}

	public function parseMessage(Message $message): void {
		$message->setMessage($message->getComment()->getMessage(), []);
		$message->setMessageType($message->getComment()->getVerb());
		$this->setActor($message);

		$event = new GenericEvent($message);
		$this->dispatcher->dispatch(self::class . '::parseMessage', $event);
	}

	protected function setActor(Message $message): void {
		$comment = $message->getComment();

		$displayName = '';
		if ($comment->getActorType() === 'users') {
			$user = $this->userManager->get($comment->getActorId());
			$displayName = $user instanceof IUser ? $user->getDisplayName() : $comment->getActorId();
		} else if ($comment->getActorType() === 'guests') {
			if (isset($guestNames[$comment->getActorId()])) {
				$displayName = $this->guestNames[$comment->getActorId()];
			} else {
				try {
					$displayName = $this->guestManager->getNameBySessionHash($comment->getActorId());
				} catch (ParticipantNotFoundException $e) {
				}
				$this->guestNames[$comment->getActorId()] = $displayName;
			}
		} else if ($comment->getActorType() === 'bots') {
			$displayName = $comment->getActorId() . '-bot';
		}

		$message->setActor(
			$comment->getActorType(),
			$comment->getActorId(),
			$displayName
		);
	}
}

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

namespace OCA\Talk\Chat;

use OCA\Talk\Events\ChatMessageEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\BotService;
use OCA\Talk\Service\ParticipantService;
use OCP\Comments\IComment;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IUserManager;

/**
 * Helper class to get a rich message from a plain text message.
 */
class MessageParser {
	public const EVENT_MESSAGE_PARSE = self::class . '::parseMessage';

	protected array $guestNames = [];
	protected array $bots = [];
	protected array $botNames = [];

	public function __construct(
		protected IEventDispatcher   $dispatcher,
		protected IUserManager       $userManager,
		protected ParticipantService $participantService,
		protected BotService         $botService,
	) {
	}

	public function createMessage(Room $room, ?Participant $participant, IComment $comment, IL10N $l): Message {
		return new Message($room, $participant, $comment, $l);
	}

	public function parseMessage(Message $message): void {
		$message->setMessage($message->getComment()->getMessage(), []);

		$verb = $message->getComment()->getVerb();
		if ($verb === ChatManager::VERB_OBJECT_SHARED) {
			$verb = ChatManager::VERB_SYSTEM;
		}
		$message->setMessageType($verb);
		$this->setActor($message);

		$event = new ChatMessageEvent($message);
		$this->dispatcher->dispatch(self::EVENT_MESSAGE_PARSE, $event);
	}

	protected function setActor(Message $message): void {
		$comment = $message->getComment();

		$actorId = $comment->getActorId();
		$displayName = '';
		if ($comment->getActorType() === Attendee::ACTOR_USERS) {
			$displayName = $this->userManager->getDisplayName($comment->getActorId()) ?? $comment->getActorId();
		} elseif ($comment->getActorType() === Attendee::ACTOR_BRIDGED) {
			$displayName = $comment->getActorId();
			$actorId = MatterbridgeManager::BRIDGE_BOT_USERID;
		} elseif ($comment->getActorType() === Attendee::ACTOR_GUESTS
			&& $comment->getActorId() === Attendee::ACTOR_ID_CLI) {
			$actorId = Attendee::ACTOR_ID_CLI;
		} elseif ($comment->getActorType() === Attendee::ACTOR_GUESTS
			&& $comment->getActorId() === Attendee::ACTOR_ID_CHANGELOG) {
			$actorId = Attendee::ACTOR_ID_CHANGELOG;
		} elseif ($comment->getActorType() === Attendee::ACTOR_GUESTS) {
			if (isset($guestNames[$comment->getActorId()])) {
				$displayName = $this->guestNames[$comment->getActorId()];
			} else {
				try {
					$participant = $this->participantService->getParticipantByActor($message->getRoom(), Attendee::ACTOR_GUESTS, $comment->getActorId());
					$displayName = $participant->getAttendee()->getDisplayName();
				} catch (ParticipantNotFoundException $e) {
				}
				$this->guestNames[$comment->getActorId()] = $displayName;
			}
		} elseif ($comment->getActorType() === Attendee::ACTOR_BOTS) {
			$actorId = $comment->getActorId();
			$displayName = $comment->getActorId() . '-bot';
			$token = $message->getRoom()->getToken();
			if (str_starts_with($actorId, Attendee::ACTOR_BOT_PREFIX)) {
				$urlHash = substr($actorId, strlen(Attendee::ACTOR_BOT_PREFIX));
				$botName = $this->getBotNameByUrlHashForConversation($token, $urlHash);
				if ($botName) {
					$displayName = $botName . ' (Bot)';
				}
			}
		} elseif ($comment->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
			// FIXME Read from some addressbooks?
			$displayName = $actorId;
		}

		$message->setActor(
			$comment->getActorType(),
			$actorId,
			$displayName
		);
	}

	protected function getBotNameByUrlHashForConversation(string $token, string $urlHash): ?string {
		if (!isset($this->botNames[$token])) {
			$this->botNames[$token] = [];
			$bots = $this->botService->getBotsForToken($token, null);
			foreach ($bots as $bot) {
				$botServer = $bot->getBotServer();
				$this->botNames[$token][$botServer->getUrlHash()] = $botServer->getName();
			}
		}

		return $this->botNames[$token][$urlHash] ?? null;
	}
}

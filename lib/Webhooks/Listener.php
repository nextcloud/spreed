<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Webhooks;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\ChatParticipantEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\TalkSession;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClientService;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Server;

class Listener {
	public function __construct(
		protected IClientService $clientService,
		protected IUserSession $userSession,
		protected TalkSession $talkSession,
		protected ISession $session,
	) {
	}

	public static function register(IEventDispatcher $dispatcher): void {
		$dispatcher->addListener(ChatManager::EVENT_AFTER_MESSAGE_SEND, [self::class, 'afterMessageSendStatic']);
		$dispatcher->addListener(Room::EVENT_AFTER_USERS_ADD, [self::class, 'afterParticipantsAddedStatic']);
	}

	public static function afterMessageSendStatic(ChatParticipantEvent $event): void {
		/** @var static $listener */
		$listener = Server::get(self::class);
		$listener->afterMessageSend($event);
	}

	public function afterMessageSend(ChatParticipantEvent $event): void {
		$attendee = $event->getParticipant()->getAttendee();
		$userId = $attendee->getActorType() === Attendee::ACTOR_USERS ? $attendee->getActorId() : '';

		$this->sendAsyncRequest([
			'type' => 'Create',
			'actor' => [
				'type' => 'Person',
				'id' => $attendee->getActorType() . '/' . $attendee->getActorId(),
				'name' => $attendee->getDisplayName(),
			],
			'object' => [
				'type' => 'Note',
				'id' => $event->getComment()->getId(),
				'name' => $event->getComment()->getMessage(),
			],
			'target' => [
				'type' => 'Collection',
				'id' => $event->getRoom()->getToken(),
				'name' => $event->getRoom()->getDisplayName($userId),
			]
		]);
	}

	public function afterParticipantsAdded(AttendeesAddedEvent $event): void {
		$actor = $this->getActor($event->getRoom());
		$userId = $actor['type'] === Attendee::ACTOR_USERS ? $actor['id'] : '';

		$data = [
			'type' => 'Add',
			'actor' => [
				'type' => 'Person',
				'id' => $actor['type'] . '/' . $actor['id'],
				'name' => $actor['name'],
			],
			'target' => [
				'type' => 'Group',
				'id' => $event->getRoom()->getToken(),
				'name' => $event->getRoom()->getDisplayName($userId),
			]
		];

		foreach ($event->getAttendees() as $attendee) {
			$data['object'] = [
				'type' => 'Person',
				'id' => $attendee->getActorType() . '/' . $attendee->getActorId(),
				'name' => $attendee->getDisplayName(),
			];
			$this->sendAsyncRequest($data);
		}
	}

	public function afterParticipantsRemoved(AttendeesRemovedEvent $event): void {
		$actor = $this->getActor($event->getRoom());
		$userId = $actor['type'] === Attendee::ACTOR_USERS ? $actor['id'] : '';

		$data = [
			'type' => 'Remove',
			'actor' => [
				'type' => 'Person',
				'id' => $actor['type'] . '/' . $actor['id'],
				'name' => $actor['name'],
			],
			'origin' => [
				'type' => 'Group',
				'id' => $event->getRoom()->getToken(),
				'name' => $event->getRoom()->getDisplayName($userId),
			]
		];

		foreach ($event->getAttendees() as $attendee) {
			$data['object'] = [
				'type' => 'Person',
				'id' => $attendee->getActorType() . '/' . $attendee->getActorId(),
				'name' => $attendee->getDisplayName(),
			];
			$this->sendAsyncRequest($data);
		}
	}

	/**
	 * @param Room $room
	 * @return array
	 * @psalm-return array{type: string, id: string, name: string}
	 */
	protected function getActor(Room $room): array {
		if (\OC::$CLI || $this->session->exists('talk-overwrite-actor-cli')) {
			return [
				'type' => Attendee::ACTOR_GUESTS,
				'id' => 'cli',
				'name' => 'Administration',
			];
		}

		if ($this->session->exists('talk-overwrite-actor-type')) {
			return [
				'type' => $this->session->get('talk-overwrite-actor-type'),
				'id' => $this->session->get('talk-overwrite-actor-id'),
				'name' => $this->session->get('talk-overwrite-actor-displayname'),
			];
		}

		if ($this->session->exists('talk-overwrite-actor-id')) {
			return [
				'type' => Attendee::ACTOR_USERS,
				'id' => $this->session->get('talk-overwrite-actor-id'),
				'name' => $this->session->get('talk-overwrite-actor-displayname'),
			];
		}

		$user = $this->userSession->getUser();
		if ($user instanceof IUser) {
			return [
				'type' => Attendee::ACTOR_USERS,
				'id' => $user->getUID(),
				'name' => $user->getDisplayName(),
			];
		}

		$sessionId = $this->talkSession->getSessionForRoom($room->getToken());
		$actorId = $sessionId ? sha1($sessionId) : 'failed-to-get-session';
		return [
			'type' => Attendee::ACTOR_GUESTS,
			'id' =>  $actorId,
			'name' => $user->getDisplayName(),
		];
	}

	protected function sendAsyncRequest(array $body): void {
		$data = [
			'verify' => false,
			'nextcloud' => [
				'allow_local_address' => true, // FIXME don't enforce
			],
			'body' => $body,
		];

		$client = $this->clientService->newClient();
		$client->postAsync('https://nextcloud26.local/hookcity.php', $data);
	}
}

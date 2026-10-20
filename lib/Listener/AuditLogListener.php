<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\AuditLog\Operation;
use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\AttendeeRemovedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\BotDisabledEvent;
use OCA\Talk\Events\BotEnabledEvent;
use OCA\Talk\Events\LobbyModifiedEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Events\RoomCreatedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BotServer;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\Log\Audit\CriticalActionPerformedEvent;

/**
 * Writes changes to conversations, their participants and their bots to the admin audit log
 *
 * @template-implements IEventListener<Event>
 */
class AuditLogListener implements IEventListener {
	public function __construct(
		private readonly IEventDispatcher $dispatcher,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof RoomCreatedEvent) {
			$this->roomCreated($event);
		} elseif ($event instanceof LobbyModifiedEvent) {
			$this->lobbyModified($event);
		} elseif ($event instanceof RoomModifiedEvent) {
			$this->roomModified($event);
		} elseif ($event instanceof AttendeesAddedEvent) {
			$this->attendeesAdded($event);
		} elseif ($event instanceof AttendeeRemovedEvent) {
			$this->attendeeRemoved($event);
		} elseif ($event instanceof ParticipantModifiedEvent) {
			$this->participantModified($event);
		} elseif ($event instanceof BotEnabledEvent) {
			$this->botToggled($event->getRoom(), $event->getBotServer(), 'enabled', Operation::CONVERSATION_BOT_ENABLED);
		} elseif ($event instanceof BotDisabledEvent) {
			$this->botToggled($event->getRoom(), $event->getBotServer(), 'disabled', Operation::CONVERSATION_BOT_DISABLED);
		}
	}

	private function roomCreated(RoomCreatedEvent $event): void {
		$room = $event->getRoom();
		if (!$this->isSharedConversation($room)) {
			return;
		}

		$this->log('Conversation "{token}" was created as {conversationType} conversation', [
			'token' => $room->getToken(),
			'conversationType' => $this->conversationTypeLabel($room->getType()),
			'objectType' => $room->getObjectType(),
			'objectId' => $room->getObjectId(),
		], Operation::CONVERSATION_CREATED);
	}

	private function lobbyModified(LobbyModifiedEvent $event): void {
		if ($event->isTimerReached()) {
			return;
		}

		$this->log('Lobby of conversation "{token}" was changed from {oldLobbyState} to {lobbyState}', [
			'token' => $event->getRoom()->getToken(),
			'oldLobbyState' => $this->formatValue($event->getOldValue()),
			'lobbyState' => $this->formatValue($event->getNewValue()),
			'lobbyTimer' => $event->getLobbyTimer()?->format(\DateTimeInterface::ATOM),
		], Operation::CONVERSATION_LOBBY_CHANGED);
	}

	private function roomModified(RoomModifiedEvent $event): void {
		$token = $event->getRoom()->getToken();

		match ($event->getProperty()) {
			ARoomModifiedEvent::PROPERTY_TYPE => $this->log('Type of conversation "{token}" was changed from {oldConversationType} to {conversationType}', [
				'token' => $token,
				'oldConversationType' => $this->conversationTypeLabel($event->getOldValue()),
				'conversationType' => $this->conversationTypeLabel($event->getNewValue()),
			], Operation::CONVERSATION_TYPE_CHANGED),
			// The value is the password itself, so only whether one is set is logged
			ARoomModifiedEvent::PROPERTY_PASSWORD => $this->log('Password protection of conversation "{token}" was {passwordProtection}', [
				'token' => $token,
				'passwordProtection' => $event->getNewValue() !== '' && $event->getNewValue() !== null ? 'enabled' : 'disabled',
			], Operation::CONVERSATION_PASSWORD_CHANGED),
			ARoomModifiedEvent::PROPERTY_LISTABLE => $this->logValueChange('Listable scope', $token, $event, Operation::CONVERSATION_LISTABLE_CHANGED),
			ARoomModifiedEvent::PROPERTY_READ_ONLY => $this->logValueChange('Read-only state', $token, $event, Operation::CONVERSATION_READ_ONLY_CHANGED),
			ARoomModifiedEvent::PROPERTY_SIP_ENABLED => $this->logValueChange('SIP dial-in state', $token, $event, Operation::CONVERSATION_SIP_CHANGED),
			ARoomModifiedEvent::PROPERTY_DEFAULT_PERMISSIONS => $this->logValueChange('Default permissions', $token, $event, Operation::CONVERSATION_PERMISSIONS_CHANGED),
			ARoomModifiedEvent::PROPERTY_MENTION_PERMISSIONS => $this->logValueChange('Mention permissions', $token, $event, Operation::CONVERSATION_MENTION_PERMISSIONS_CHANGED),
			ARoomModifiedEvent::PROPERTY_MESSAGE_EXPIRATION => $this->logValueChange('Message expiration', $token, $event, Operation::CONVERSATION_MESSAGE_EXPIRATION_CHANGED),
			ARoomModifiedEvent::PROPERTY_RECORDING_CONSENT => $this->logValueChange('Recording consent requirement', $token, $event, Operation::CONVERSATION_RECORDING_CONSENT_CHANGED),
			ARoomModifiedEvent::PROPERTY_CALL_RECORDING => $this->callRecordingModified($token, $event),
			default => null,
		};
	}

	/**
	 * Only the confirmed start and the end of a recording are logged, not the transitional states
	 */
	private function callRecordingModified(string $token, RoomModifiedEvent $event): void {
		$newStatus = $event->getNewValue();
		$oldStatus = $event->getOldValue();

		if ($newStatus === Room::RECORDING_VIDEO || $newStatus === Room::RECORDING_AUDIO) {
			$this->log('Recording of conversation "{token}" was started ({recordingType})', [
				'token' => $token,
				'recordingType' => $newStatus === Room::RECORDING_VIDEO ? 'video' : 'audio',
			], Operation::RECORDING_STARTED);
		} elseif ($newStatus === Room::RECORDING_NONE && in_array($oldStatus, [Room::RECORDING_VIDEO, Room::RECORDING_AUDIO], true)) {
			$this->log('Recording of conversation "{token}" was stopped', [
				'token' => $token,
			], Operation::RECORDING_STOPPED);
		}
	}

	private function attendeesAdded(AttendeesAddedEvent $event): void {
		$room = $event->getRoom();
		if (!$this->isSharedConversation($room)) {
			return;
		}

		foreach ($event->getAttendees() as $attendee) {
			if ($attendee->getActorType() === Attendee::ACTOR_GUESTS) {
				continue;
			}

			$this->log('{actorType} "{actorId}" was added to conversation "{token}" as {participantType}', [
				'actorId' => $this->getLoggedActorId($attendee),
				'actorType' => $attendee->getActorType(),
				'token' => $room->getToken(),
				'participantType' => $this->participantTypeLabel($attendee->getParticipantType()),
			], Operation::PARTICIPANT_ADDED);
		}
	}

	private function attendeeRemoved(AttendeeRemovedEvent $event): void {
		$room = $event->getRoom();
		$attendee = $event->getAttendee();
		if (!$this->isSharedConversation($room) || $attendee->getActorType() === Attendee::ACTOR_GUESTS) {
			return;
		}

		$this->log('{actorType} "{actorId}" was removed from conversation "{token}" (reason: {reason})', [
			'actorId' => $this->getLoggedActorId($attendee),
			'actorType' => $attendee->getActorType(),
			'token' => $room->getToken(),
			'reason' => $event->getReason(),
		], Operation::PARTICIPANT_REMOVED);
	}

	private function participantModified(ParticipantModifiedEvent $event): void {
		$attendee = $event->getParticipant()->getAttendee();
		$parameters = [
			'actorId' => $this->getLoggedActorId($attendee),
			'actorType' => $attendee->getActorType(),
			'token' => $event->getRoom()->getToken(),
		];

		match ($event->getProperty()) {
			AParticipantModifiedEvent::PROPERTY_TYPE => $this->log('Participant type of {actorType} "{actorId}" in conversation "{token}" was changed from {oldParticipantType} to {participantType}', $parameters + [
				'oldParticipantType' => $event->getOldValue() === null ? null : $this->participantTypeLabel((int)$event->getOldValue()),
				'participantType' => $this->participantTypeLabel((int)$event->getNewValue()),
			], Operation::PARTICIPANT_TYPE_CHANGED),
			AParticipantModifiedEvent::PROPERTY_PERMISSIONS => $this->log('Permissions of {actorType} "{actorId}" in conversation "{token}" were changed from {oldPermissions} to {permissions}', $parameters + [
				'oldPermissions' => $event->getOldValue(),
				'permissions' => $event->getNewValue(),
			], Operation::PARTICIPANT_PERMISSIONS_CHANGED),
			default => null,
		};
	}

	private function botToggled(Room $room, BotServer $bot, string $action, Operation $operation): void {
		$this->log('Bot #{botId} ("{botName}") was ' . $action . ' in conversation "{token}"', [
			'botId' => $bot->getId(),
			'botName' => $bot->getName(),
			'token' => $room->getToken(),
		], $operation);
	}

	/**
	 * Email guests are identified by their attendee ID instead of the email address
	 */
	private function getLoggedActorId(Attendee $attendee): ?string {
		return $attendee->getActorType() === Attendee::ACTOR_EMAILS ? null : $attendee->getActorId();
	}

	/**
	 * One-to-one, note-to-self and changelog conversations are managed by the server and not audited
	 */
	private function isSharedConversation(Room $room): bool {
		return $room->getType() === Room::TYPE_GROUP || $room->getType() === Room::TYPE_PUBLIC;
	}

	private function participantTypeLabel(int $participantType): string {
		return match ($participantType) {
			Participant::OWNER => 'owner',
			Participant::MODERATOR => 'moderator',
			Participant::USER => 'user',
			Participant::GUEST => 'guest',
			Participant::USER_SELF_JOINED => 'self-joined user',
			Participant::GUEST_MODERATOR => 'guest moderator',
			default => 'unknown',
		};
	}

	private function logValueChange(string $setting, string $token, RoomModifiedEvent $event, Operation $operation): void {
		$this->log($setting . ' of conversation "{token}" was changed from {oldValue} to {newValue}', [
			'token' => $token,
			'oldValue' => $this->formatValue($event->getOldValue()),
			'newValue' => $this->formatValue($event->getNewValue()),
		], $operation);
	}

	private function formatValue(\DateTime|string|int|bool|null $value): string|int|bool|null {
		return $value instanceof \DateTime ? $value->format(\DateTimeInterface::ATOM) : $value;
	}

	private function conversationTypeLabel(\DateTime|string|int|bool|null $type): string {
		return match ($type) {
			Room::TYPE_ONE_TO_ONE => 'one-to-one',
			Room::TYPE_GROUP => 'group',
			Room::TYPE_PUBLIC => 'public',
			Room::TYPE_CHANGELOG => 'changelog',
			Room::TYPE_ONE_TO_ONE_FORMER => 'former one-to-one',
			Room::TYPE_NOTE_TO_SELF => 'note-to-self',
			default => 'unknown',
		};
	}

	/**
	 * @param array<string, scalar|null> $parameters
	 */
	private function log(string $message, array $parameters, Operation $operation): void {
		$this->dispatcher->dispatchTyped(new CriticalActionPerformedEvent($message, $parameters, false, $operation->value));
	}
}

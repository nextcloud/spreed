<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\AuditLog\Operation;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\LobbyModifiedEvent;
use OCA\Talk\Events\RoomCreatedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Room;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\Log\Audit\CriticalActionPerformedEvent;

/**
 * Writes changes to conversations to the admin audit log
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
		}
	}

	private function roomCreated(RoomCreatedEvent $event): void {
		$room = $event->getRoom();
		if ($room->getType() !== Room::TYPE_GROUP && $room->getType() !== Room::TYPE_PUBLIC) {
			return;
		}

		$this->log('Conversation "%s" was created as %s conversation', [
			'token' => $room->getToken(),
			'conversationType' => $this->conversationTypeLabel($room->getType()),
			'objectType' => $room->getObjectType(),
			'objectId' => $room->getObjectId(),
		], Operation::ConversationCreated);
	}

	private function lobbyModified(LobbyModifiedEvent $event): void {
		if ($event->isTimerReached()) {
			return;
		}

		$this->log('Lobby of conversation "%s" was changed from %s to %s', [
			'token' => $event->getRoom()->getToken(),
			'oldLobbyState' => $this->formatValue($event->getOldValue()),
			'lobbyState' => $this->formatValue($event->getNewValue()),
			'lobbyTimer' => $event->getLobbyTimer()?->format(\DateTimeInterface::ATOM),
		], Operation::ConversationLobbyChanged);
	}

	private function roomModified(RoomModifiedEvent $event): void {
		$token = $event->getRoom()->getToken();

		match ($event->getProperty()) {
			ARoomModifiedEvent::PROPERTY_TYPE => $this->log('Type of conversation "%s" was changed from %s to %s', [
				'token' => $token,
				'oldConversationType' => $this->conversationTypeLabel($event->getOldValue()),
				'conversationType' => $this->conversationTypeLabel($event->getNewValue()),
			], Operation::ConversationTypeChanged),
			// The value is the password itself, so only whether one is set is logged
			ARoomModifiedEvent::PROPERTY_PASSWORD => $this->log('Password protection of conversation "%s" was %s', [
				'token' => $token,
				'passwordProtection' => $event->getNewValue() !== '' && $event->getNewValue() !== null ? 'enabled' : 'disabled',
			], Operation::ConversationPasswordChanged),
			ARoomModifiedEvent::PROPERTY_LISTABLE => $this->logValueChange('Listable scope', $token, $event, Operation::ConversationListableChanged),
			ARoomModifiedEvent::PROPERTY_READ_ONLY => $this->logValueChange('Read-only state', $token, $event, Operation::ConversationReadOnlyChanged),
			ARoomModifiedEvent::PROPERTY_SIP_ENABLED => $this->logValueChange('SIP dial-in state', $token, $event, Operation::ConversationSipChanged),
			ARoomModifiedEvent::PROPERTY_DEFAULT_PERMISSIONS => $this->logValueChange('Default permissions', $token, $event, Operation::ConversationPermissionsChanged),
			ARoomModifiedEvent::PROPERTY_MENTION_PERMISSIONS => $this->logValueChange('Mention permissions', $token, $event, Operation::ConversationMentionPermissionsChanged),
			ARoomModifiedEvent::PROPERTY_MESSAGE_EXPIRATION => $this->logValueChange('Message expiration', $token, $event, Operation::ConversationMessageExpirationChanged),
			ARoomModifiedEvent::PROPERTY_RECORDING_CONSENT => $this->logValueChange('Recording consent requirement', $token, $event, Operation::ConversationRecordingConsentChanged),
			default => null,
		};
	}

	private function logValueChange(string $setting, string $token, RoomModifiedEvent $event, Operation $operation): void {
		$this->log($setting . ' of conversation "%s" was changed from %s to %s', [
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

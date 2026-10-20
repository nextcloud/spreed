<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Talk\Events\AAttendeeRemovedEvent;
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
use OCA\Talk\Listener\AuditLogListener;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BotServer;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Webinary;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AuditLogListenerTest extends TestCase {
	protected IEventDispatcher&MockObject $dispatcher;
	protected AuditLogListener $listener;
	/** @var list<CriticalActionPerformedEvent> */
	protected array $auditEvents = [];

	public function setUp(): void {
		parent::setUp();

		$this->auditEvents = [];
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->dispatcher->method('dispatchTyped')
			->willReturnCallback(function (Event $event): void {
				if ($event instanceof CriticalActionPerformedEvent) {
					$this->auditEvents[] = $event;
				}
			});
		$this->listener = new AuditLogListener($this->dispatcher);
	}

	protected function createRoom(int $type = Room::TYPE_GROUP): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getToken')->willReturn('abcdef');
		$room->method('getType')->willReturn($type);
		$room->method('getObjectType')->willReturn('file');
		$room->method('getObjectId')->willReturn('42');
		return $room;
	}

	protected function assertAuditEvent(string $operation, string $message, array $parameters): void {
		$this->assertCount(1, $this->auditEvents);
		$this->assertSame($operation, $this->auditEvents[0]->getOperation());
		$this->assertSame($message, $this->auditEvents[0]->getLogMessage());
		$this->assertSame($parameters, $this->auditEvents[0]->getParameters());
	}

	public function testRoomCreated(): void {
		$this->listener->handle(new RoomCreatedEvent($this->createRoom(Room::TYPE_PUBLIC)));

		$this->assertAuditEvent('spreed.conversation.created', 'Conversation "{token}" was created as {conversationType} conversation', [
			'token' => 'abcdef',
			'conversationType' => 'public',
			'objectType' => 'file',
			'objectId' => '42',
		]);
	}

	public static function dataRoomCreatedIgnored(): array {
		return [
			[Room::TYPE_ONE_TO_ONE],
			[Room::TYPE_NOTE_TO_SELF],
			[Room::TYPE_CHANGELOG],
		];
	}

	#[DataProvider('dataRoomCreatedIgnored')]
	public function testRoomCreatedIgnored(int $type): void {
		$this->listener->handle(new RoomCreatedEvent($this->createRoom($type)));

		$this->assertSame([], $this->auditEvents);
	}

	public function testTypeChanged(): void {
		$this->listener->handle(new RoomModifiedEvent($this->createRoom(), ARoomModifiedEvent::PROPERTY_TYPE, Room::TYPE_PUBLIC, Room::TYPE_GROUP));

		$this->assertAuditEvent('spreed.conversation_type.changed', 'Type of conversation "{token}" was changed from {oldConversationType} to {conversationType}', [
			'token' => 'abcdef',
			'oldConversationType' => 'group',
			'conversationType' => 'public',
		]);
	}

	public static function dataPasswordChanged(): array {
		return [
			['secret-password', 'enabled'],
			['', 'disabled'],
		];
	}

	#[DataProvider('dataPasswordChanged')]
	public function testPasswordChanged(string $password, string $expected): void {
		$this->listener->handle(new RoomModifiedEvent($this->createRoom(), ARoomModifiedEvent::PROPERTY_PASSWORD, $password));

		$this->assertAuditEvent('spreed.conversation_password.changed', 'Password protection of conversation "{token}" was {passwordProtection}', [
			'token' => 'abcdef',
			'passwordProtection' => $expected,
		]);
		$this->assertNotContains('secret-password', $this->auditEvents[0]->getParameters());
	}

	public static function dataValueChanged(): array {
		return [
			[ARoomModifiedEvent::PROPERTY_LISTABLE, Room::LISTABLE_ALL, Room::LISTABLE_NONE, 'Listable scope', 'spreed.conversation_listable.changed'],
			[ARoomModifiedEvent::PROPERTY_READ_ONLY, Room::READ_ONLY, Room::READ_WRITE, 'Read-only state', 'spreed.conversation_read_only.changed'],
			[ARoomModifiedEvent::PROPERTY_SIP_ENABLED, Webinary::SIP_ENABLED, Webinary::SIP_DISABLED, 'SIP dial-in state', 'spreed.conversation_sip.changed'],
			[ARoomModifiedEvent::PROPERTY_DEFAULT_PERMISSIONS, 254, 0, 'Default permissions', 'spreed.conversation_permissions.changed'],
			[ARoomModifiedEvent::PROPERTY_MENTION_PERMISSIONS, 1, 0, 'Mention permissions', 'spreed.conversation_mention_permissions.changed'],
			[ARoomModifiedEvent::PROPERTY_MESSAGE_EXPIRATION, 3600, 0, 'Message expiration', 'spreed.conversation_message_expiration.changed'],
			[ARoomModifiedEvent::PROPERTY_RECORDING_CONSENT, 1, 0, 'Recording consent requirement', 'spreed.conversation_recording_consent.changed'],
		];
	}

	#[DataProvider('dataValueChanged')]
	public function testValueChanged(string $property, int $newValue, int $oldValue, string $setting, string $operation): void {
		$this->listener->handle(new RoomModifiedEvent($this->createRoom(), $property, $newValue, $oldValue));

		$this->assertAuditEvent($operation, $setting . ' of conversation "{token}" was changed from {oldValue} to {newValue}', [
			'token' => 'abcdef',
			'oldValue' => $oldValue,
			'newValue' => $newValue,
		]);
	}

	public function testUnauditedPropertyIsIgnored(): void {
		$this->listener->handle(new RoomModifiedEvent($this->createRoom(), ARoomModifiedEvent::PROPERTY_NAME, 'New name', 'Old name'));

		$this->assertSame([], $this->auditEvents);
	}

	public function testLobbyModified(): void {
		$timer = new \DateTime('2026-10-16T12:00:00+00:00');
		$this->listener->handle(new LobbyModifiedEvent($this->createRoom(), Webinary::LOBBY_NON_MODERATORS, Webinary::LOBBY_NONE, $timer, false));

		$this->assertAuditEvent('spreed.conversation_lobby.changed', 'Lobby of conversation "{token}" was changed from {oldLobbyState} to {lobbyState}', [
			'token' => 'abcdef',
			'oldLobbyState' => Webinary::LOBBY_NONE,
			'lobbyState' => Webinary::LOBBY_NON_MODERATORS,
			'lobbyTimer' => '2026-10-16T12:00:00+00:00',
		]);
	}

	public function testLobbyTimerReachedIsIgnored(): void {
		$this->listener->handle(new LobbyModifiedEvent($this->createRoom(), Webinary::LOBBY_NONE, Webinary::LOBBY_NON_MODERATORS, null, true));

		$this->assertSame([], $this->auditEvents);
	}

	protected function createAttendee(string $actorType, string $actorId, int $participantType = Participant::USER, int $id = 7): Attendee {
		$attendee = new Attendee();
		$attendee->setId($id);
		$attendee->setActorType($actorType);
		$attendee->setActorId($actorId);
		$attendee->setParticipantType($participantType);
		return $attendee;
	}

	public function testAttendeesAdded(): void {
		$this->listener->handle(new AttendeesAddedEvent($this->createRoom(), [
			$this->createAttendee(Attendee::ACTOR_USERS, 'alice', Participant::MODERATOR, 7),
			$this->createAttendee(Attendee::ACTOR_GUESTS, 'guest-session', Participant::GUEST, 8),
			$this->createAttendee(Attendee::ACTOR_EMAILS, 'invite-id', Participant::GUEST, 9),
		]));

		$this->assertCount(2, $this->auditEvents);
		$this->assertSame('spreed.participant.added', $this->auditEvents[0]->getOperation());
		$this->assertSame('{actorType} "{actorId}" was added to conversation "{token}" as {participantType}', $this->auditEvents[0]->getLogMessage());
		$this->assertSame([
			'actorId' => 'alice',
			'actorType' => 'users',
			'token' => 'abcdef',
			'participantType' => 'moderator',
		], $this->auditEvents[0]->getParameters());
		$this->assertSame([
			'actorId' => null,
			'actorType' => 'emails',
			'token' => 'abcdef',
			'participantType' => 'guest',
		], $this->auditEvents[1]->getParameters());
	}

	public function testAttendeesAddedToOneToOneIsIgnored(): void {
		$this->listener->handle(new AttendeesAddedEvent($this->createRoom(Room::TYPE_ONE_TO_ONE), [
			$this->createAttendee(Attendee::ACTOR_USERS, 'alice'),
		]));

		$this->assertSame([], $this->auditEvents);
	}

	public function testAttendeeRemoved(): void {
		$this->listener->handle(new AttendeeRemovedEvent($this->createRoom(), $this->createAttendee(Attendee::ACTOR_GROUPS, 'admins'), AAttendeeRemovedEvent::REASON_REMOVED, []));

		$this->assertAuditEvent('spreed.participant.removed', '{actorType} "{actorId}" was removed from conversation "{token}" (reason: {reason})', [
			'actorId' => 'admins',
			'actorType' => 'groups',
			'token' => 'abcdef',
			'reason' => 'remove',
		]);
	}

	public function testGuestRemovedIsIgnored(): void {
		$this->listener->handle(new AttendeeRemovedEvent($this->createRoom(), $this->createAttendee(Attendee::ACTOR_GUESTS, 'guest-session', Participant::GUEST), AAttendeeRemovedEvent::REASON_LEFT, []));

		$this->assertSame([], $this->auditEvents);
	}

	public function testParticipantTypeChanged(): void {
		$room = $this->createRoom();
		$participant = new Participant($room, $this->createAttendee(Attendee::ACTOR_USERS, 'alice'), null);
		$this->listener->handle(new ParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_TYPE, Participant::MODERATOR, Participant::USER));

		$this->assertAuditEvent('spreed.participant_type.changed', 'Participant type of {actorType} "{actorId}" in conversation "{token}" was changed from {oldParticipantType} to {participantType}', [
			'actorId' => 'alice',
			'actorType' => 'users',
			'token' => 'abcdef',
			'oldParticipantType' => 'user',
			'participantType' => 'moderator',
		]);
	}

	public function testParticipantPermissionsChanged(): void {
		$room = $this->createRoom();
		$participant = new Participant($room, $this->createAttendee(Attendee::ACTOR_USERS, 'alice'), null);
		$this->listener->handle(new ParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_PERMISSIONS, 254, 0));

		$this->assertAuditEvent('spreed.participant_permissions.changed', 'Permissions of {actorType} "{actorId}" in conversation "{token}" were changed from {oldPermissions} to {permissions}', [
			'actorId' => 'alice',
			'actorType' => 'users',
			'token' => 'abcdef',
			'oldPermissions' => 0,
			'permissions' => 254,
		]);
	}

	public function testParticipantNameChangeIsIgnored(): void {
		$room = $this->createRoom();
		$participant = new Participant($room, $this->createAttendee(Attendee::ACTOR_GUESTS, 'guest-session', Participant::GUEST), null);
		$this->listener->handle(new ParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_NAME, 'Guest name'));

		$this->assertSame([], $this->auditEvents);
	}

	public static function dataCallRecording(): array {
		return [
			[Room::RECORDING_VIDEO, Room::RECORDING_VIDEO_STARTING, 'spreed.recording.started', 'Recording of conversation "{token}" was started ({recordingType})', ['token' => 'abcdef', 'recordingType' => 'video']],
			[Room::RECORDING_AUDIO, Room::RECORDING_AUDIO_STARTING, 'spreed.recording.started', 'Recording of conversation "{token}" was started ({recordingType})', ['token' => 'abcdef', 'recordingType' => 'audio']],
			[Room::RECORDING_NONE, Room::RECORDING_VIDEO, 'spreed.recording.stopped', 'Recording of conversation "{token}" was stopped', ['token' => 'abcdef']],
		];
	}

	#[DataProvider('dataCallRecording')]
	public function testCallRecording(int $newStatus, int $oldStatus, string $operation, string $message, array $parameters): void {
		$this->listener->handle(new RoomModifiedEvent($this->createRoom(), ARoomModifiedEvent::PROPERTY_CALL_RECORDING, $newStatus, $oldStatus));

		$this->assertAuditEvent($operation, $message, $parameters);
	}

	public static function dataCallRecordingTransitionIgnored(): array {
		return [
			[Room::RECORDING_VIDEO_STARTING, Room::RECORDING_NONE],
			[Room::RECORDING_FAILED, Room::RECORDING_VIDEO_STARTING],
			[Room::RECORDING_NONE, Room::RECORDING_FAILED],
		];
	}

	#[DataProvider('dataCallRecordingTransitionIgnored')]
	public function testCallRecordingTransitionIgnored(int $newStatus, int $oldStatus): void {
		$this->listener->handle(new RoomModifiedEvent($this->createRoom(), ARoomModifiedEvent::PROPERTY_CALL_RECORDING, $newStatus, $oldStatus));

		$this->assertSame([], $this->auditEvents);
	}

	public function testBotEnabledAndDisabled(): void {
		$bot = new BotServer();
		$bot->setId(3);
		$bot->setName('Helper');
		$room = $this->createRoom();

		$this->listener->handle(new BotEnabledEvent($room, $bot));
		$this->listener->handle(new BotDisabledEvent($room, $bot));

		$this->assertCount(2, $this->auditEvents);
		$this->assertSame('spreed.conversation_bot.enabled', $this->auditEvents[0]->getOperation());
		$this->assertSame('Bot #{botId} ("{botName}") was enabled in conversation "{token}"', $this->auditEvents[0]->getLogMessage());
		$this->assertSame(['botId' => 3, 'botName' => 'Helper', 'token' => 'abcdef'], $this->auditEvents[0]->getParameters());
		$this->assertSame('spreed.conversation_bot.disabled', $this->auditEvents[1]->getOperation());
	}
}

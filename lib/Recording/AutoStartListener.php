<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Recording;

use InvalidArgumentException;
use OCA\Talk\Config;
use OCA\Talk\Events\CallStartedEvent;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RecordingService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Starts a conversation's recording when its call begins, provided a moderator
 * enabled automatic recording for that conversation.
 *
 * This intentionally runs on every call start, not only when a moderator joins:
 * the setting is the authorisation, and the room owner is a stable local
 * account for the recording file even when a guest or a non-moderator started
 * the call.
 *
 * @template-implements IEventListener<Event>
 */
class AutoStartListener implements IEventListener {
	public function __construct(
		private readonly RecordingService $recordingService,
		private readonly ParticipantService $participantService,
		private readonly Config $config,
		private readonly LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof CallStartedEvent) {
			return;
		}

		$room = $event->getRoom();
		if (!$room->isRecordingAutoStart()
			|| $room->isClassified()
			|| $room->isFederatedConversation()
			|| $room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE
			|| !$this->config->isRecordingEnabled()) {
			return;
		}

		if (!in_array($room->getCallRecording(), [Room::RECORDING_NONE, Room::RECORDING_FAILED], true)) {
			return;
		}

		$owner = $this->participantService->getRoomOwner($room);
		if ($owner === null) {
			$this->logger->warning('Automatic recording skipped: conversation has no owner', [
				'token' => $room->getToken(),
			]);
			return;
		}

		try {
			$this->recordingService->start(
				$room,
				Room::RECORDING_VIDEO,
				$owner->getAttendee()->getActorId(),
				$owner,
			);
		} catch (InvalidArgumentException $e) {
			// The recording backend is unavailable, the call ended before the
			// event was handled, or a recording raced us. Leave the call alone;
			// the normal recording controls still work.
			$this->logger->warning('Automatic recording could not start', [
				'token' => $room->getToken(),
				'reason' => $e->getMessage(),
			]);
		}
	}
}

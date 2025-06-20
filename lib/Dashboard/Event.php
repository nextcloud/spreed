<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Dashboard;

use OCA\Talk\ResponseDefinitions;

/**
 * @psalm-import-type TalkDashboardEvent from ResponseDefinitions
 * @psalm-import-type TalkDashboardEventCalendar from ResponseDefinitions
 * @psalm-import-type TalkDashboardEventAttachment from ResponseDefinitions
 */
class Event implements \JsonSerializable {
	/** @var non-empty-list<TalkDashboardEventCalendar> */
	protected array $calendars = [];
	protected string $eventName = '';
	protected string $eventLink = '';
	protected int $start = 0;
	protected int $end = 0;
	protected string $roomToken = '';
	protected string $roomAvatarVersion = '';
	protected string $roomName = '';
	protected string $roomDisplayName = '';
	protected int $roomType = 0;
	protected ?string $eventDescription = null;
	/** @var array<string, TalkDashboardEventAttachment> */
	protected array $eventAttachments = [];
	protected ?int $roomActiveSince = null;
	protected ?int $accepted = null;
	protected ?int $tentative = null;
	protected ?int $declined = null;
	protected ?int $invited = null;

	public function __construct() {
	}

	/**
	 * @return non-empty-list<TalkDashboardEventCalendar>
	 */
	public function getCalendars(): array {
		return $this->calendars;
	}

	public function getEventName(): string {
		return $this->eventName;
	}

	public function setEventName(string $eventName): void {
		$this->eventName = $eventName;
	}

	public function getEventDescription(): ?string {
		return $this->eventDescription;
	}

	public function setEventDescription(?string $eventDescription): void {
		$this->eventDescription = $eventDescription;
	}

	/**
	 * @return array<string, TalkDashboardEventAttachment>
	 */
	public function getEventAttachments(): array {
		return $this->eventAttachments;
	}

	public function setEventLink(string $eventLink): void {
		$this->eventLink = $eventLink;
	}

	public function getStart(): int {
		return $this->start;
	}

	public function setStart(int $start): void {
		$this->start = $start;
	}

	public function getEnd(): int {
		return $this->end;
	}

	public function setEnd(int $end): void {
		$this->end = $end;
	}

	public function setRoomToken(string $roomToken): void {
		$this->roomToken = $roomToken;
	}

	public function setRoomAvatarVersion(string $roomAvatarVersion): void {
		$this->roomAvatarVersion = $roomAvatarVersion;
	}

	public function setRoomName(string $roomName): void {
		$this->roomName = $roomName;
	}

	public function setRoomDisplayName(string $roomDisplayName): void {
		$this->roomDisplayName = $roomDisplayName;
	}

	public function setRoomType(int $roomType): void {
		$this->roomType = $roomType;
	}

	public function setRoomActiveSince(?int $roomActiveSince): void {
		$this->roomActiveSince = $roomActiveSince;
	}

	public function generateAttendance(array $attendees): void {
		foreach ($attendees as $attendee) {
			if (!isset($attendee[1]['PARTSTAT'])) {
				continue;
			}

			switch ($attendee[1]['PARTSTAT']->getValue()) {
				case 'ACCEPTED':
					(int)$this->accepted++;
					break;
				case 'TENTATIVE':
					(int)$this->tentative++;
					break;
				case 'DECLINED':
					(int)$this->declined++;
					break;
				case 'NEEDS-ACTION':
					(int)$this->invited++;
					break;
				default:
					break;
			}
		}
	}

	public function isAttendee(array $attendees, string $email): bool {
		foreach ($attendees as $attendee) {
			if (!isset($attendee[1]['PARTSTAT'])) {
				continue;
			}

			// Calendar emails start with 'mailto:'
			if (substr($attendee[0], 7) === $email) {
				return true;
			}
		}

		return false;
	}


	public function isOrganizer(array $organizer, string $email): bool {
		// Calendar emails start with 'mailto:'
		return substr($organizer[0], 7) === $email;
	}

	/**
	 * Takes the room token, start and end time and attendees to build an identifier
	 * If the identifier already exists, another event is happening at the same time
	 * in the same room
	 *
	 * We only return duplicates if the attendees are different
	 *
	 * @return string
	 */
	public function generateEventIdentifier(): string {
		return $this->roomToken . '#' . $this->start . '#' . $this->end . '#' . (int)$this->accepted . '#' . (int)$this->tentative . '#' . (int)$this->declined;
	}

	/**
	 * @param string $calendarName
	 * @param array $attachments
	 * @return void
	 */
	public function handleCalendarAttachments(string $calendarName, array $attachments): void {
		foreach ($attachments as $attachment) {
			$params = $attachment[1];
			if (!isset($params['X-NC-FILE-ID'])) {
				continue;
			}

			$this->eventAttachments[$attachment[0]] = [
				'calendars' => [$calendarName],
				'fmttype' => $params['FMTTYPE']?->getValue() ?? '',
				'filename' => $params['FILENAME']?->getValue() ?? '',
				'fileid' => $params['X-NC-FILE-ID']->getValue(),
				'preview' => $params['X-NC-HAS-PREVIEW']?->getValue() ?? false,
				'previewLink' => $params['X-NC-HAS-PREVIEW']?->getValue() ? $attachment[0] : null,
			];
		}
	}

	/**
	 * @param string $principalUri
	 * @param string $calendarName
	 * @param string|null $calendarColor
	 * @return void
	 */
	public function addCalendar(string $principalUri, string $calendarName, ?string $calendarColor): void {
		$this->calendars[] = [
			'principalUri' => $principalUri,
			'calendarName' => $calendarName,
			'calendarColor' => $calendarColor,
		];
	}

	public function mergeAttachments(self $event): void {
		$attachments = $event->getEventAttachments();

		if (empty($attachments) === true) {
			return;
		}

		if (empty($this->eventAttachments) === true) {
			$this->eventAttachments = $attachments;
			return;
		}

		foreach ($attachments as $filename => $attachment) {
			if (isset($this->eventAttachments[$filename])) {
				$this->eventAttachments[$filename]['calendars']
					= array_merge($this->eventAttachments[$filename]['calendars'], $attachment['calendars']);
			} else {
				$this->eventAttachments[$filename] = $attachment;
			}
		}
	}

	public function getEventLink(): string {
		return $this->eventLink;
	}

	public function getRoomToken(): string {
		return $this->roomToken;
	}

	public function getRoomAvatarVersion(): string {
		return $this->roomAvatarVersion;
	}

	public function getRoomName(): string {
		return $this->roomName;
	}

	public function getRoomDisplayName(): string {
		return $this->roomDisplayName;
	}

	public function getRoomType(): int {
		return $this->roomType;
	}

	public function getRoomActiveSince(): ?int {
		return $this->roomActiveSince;
	}

	public function getAccepted(): ?int {
		return $this->accepted;
	}

	public function getTentative(): ?int {
		return $this->tentative;
	}

	public function getDeclined(): ?int {
		return $this->declined;
	}

	public function getInvited(): ?int {
		return $this->invited;
	}

	/**
	 * @return TalkDashboardEvent
	 */
	#[\Override]
	public function jsonSerialize(): array {
		return [
			'calendars' => $this->getCalendars(),
			'eventName' => $this->getEventName(),
			'eventLink' => $this->getEventLink(),
			'start' => $this->getStart(),
			'end' => $this->getEnd(),
			'roomToken' => $this->getRoomToken(),
			'roomAvatarVersion' => $this->getRoomAvatarVersion(),
			'roomName' => $this->getRoomName(),
			'roomDisplayName' => $this->getRoomDisplayName(),
			'roomType' => $this->getRoomType(),
			'eventDescription' => $this->getEventDescription(),
			'eventAttachments' => $this->getEventAttachments(),
			'roomActiveSince' => $this->getRoomActiveSince(),
			'accepted' => $this->getAccepted(),
			'tentative' => $this->getTentative(),
			'declined' => $this->getDeclined(),
			'invited' => $this->getInvited(),
		];
	}
}

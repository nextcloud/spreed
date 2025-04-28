<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk;

use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\BeforeEmailInvitationSentEvent;
use OCA\Talk\Events\BeforeParticipantModifiedEvent;
use OCA\Talk\Events\EmailInvitationSentEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Exceptions\GuestImportException;
use OCA\Talk\Exceptions\RoomProperty\TypeException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\PollService;
use OCA\Talk\Service\RoomService;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\Util;
use Psr\Log\LoggerInterface;

class GuestManager {
	public function __construct(
		protected Config $talkConfig,
		protected IMailer $mailer,
		protected Defaults $defaults,
		protected IUserSession $userSession,
		protected ParticipantService $participantService,
		protected PollService $pollService,
		protected RoomService $roomService,
		protected IURLGenerator $url,
		protected IL10N $l,
		protected IEventDispatcher $dispatcher,
		protected LoggerInterface $logger,
		protected IDateTimeZone $dateTimeZone,
	) {
	}

	/**
	 * @param Room $room
	 * @param Participant $participant
	 * @param string $displayName
	 */
	public function updateName(Room $room, Participant $participant, string $displayName): void {
		$attendee = $participant->getAttendee();
		if ($attendee->getDisplayName() !== $displayName) {
			$event = new BeforeParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_NAME, $displayName);
			$this->dispatcher->dispatchTyped($event);

			$this->participantService->updateDisplayNameForActor(
				$attendee->getActorType(),
				$attendee->getActorId(),
				$displayName
			);

			$this->pollService->updateDisplayNameForActor(
				$attendee->getActorType(),
				$attendee->getActorId(),
				$displayName
			);

			$attendee->setDisplayName($displayName);

			$event = new ParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_NAME, $displayName);
			$this->dispatcher->dispatchTyped($event);
		}
	}

	public function validateMailAddress(string $email): bool {
		return $this->mailer->validateMailAddress($email);
	}

	/**
	 * @return array{invites: non-negative-int, duplicates: non-negative-int, invalid?: non-negative-int, invalidLines?: list<non-negative-int>, type?: int<-1, 6>}
	 * @throws GuestImportException
	 */
	public function importEmails(Room $room, string $filePath, bool $testRun): array {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE
			|| $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER
			|| $room->getType() === Room::TYPE_NOTE_TO_SELF
			|| $room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE
			|| $room->getObjectType() === Room::OBJECT_TYPE_VIDEO_VERIFICATION) {
			throw new GuestImportException(GuestImportException::REASON_ROOM);
		}

		$content = fopen($filePath, 'rb');
		$details = fgetcsv($content, escape: '');

		$emailKey = $nameKey = null;
		foreach ($details as $key => $header) {
			if (strtolower($header) === 'email') {
				$emailKey = $key;
			} elseif (strtolower($header) === 'name') {
				$nameKey = $key;
			}
		}

		if ($emailKey === null) {
			throw new GuestImportException(
				GuestImportException::REASON_HEADER_EMAIL,
				$this->l->t('Missing email field in header line'),
			);
		}

		if ($nameKey === null) {
			$this->logger->debug('No name field in header line, skipping name import');
		}

		$participants = $this->participantService->getParticipantsByActorType($room, Attendee::ACTOR_EMAILS);
		$alreadyInvitedEmails = array_flip(array_map(static fn (Participant $participant): string => $participant->getAttendee()->getInvitedCloudId(), $participants));

		$line = 1;
		$duplicates = 0;
		$emailsToAdd = $invalidLines = [];
		while ($details = fgetcsv($content, escape: '')) {
			$line++;
			if (isset($alreadyInvitedEmails[$details[$emailKey]])) {
				$this->logger->debug('Skipping import of ' . $details[$emailKey] . ' (line: ' . $line . ') as they are already invited');
				$duplicates++;
				continue;
			}

			if (!isset($details[$emailKey])) {
				$this->logger->debug('Invalid entry without email fields on line: ' . $line);
				$invalidLines[] = $line;
				continue;
			}

			$email = strtolower(trim($details[$emailKey]));
			if ($nameKey !== null && isset($details[$nameKey])) {
				$name = trim($details[$nameKey]);
				if ($name === '' || strcasecmp($name, $email) === 0) {
					$name = null;
				}
			} else {
				$name = null;
			}

			if (!$this->validateMailAddress($email)) {
				$this->logger->debug('Invalid email "' . $email . '" on line: ' . $line);
				$invalidLines[] = $line;
				continue;
			}

			$actorId = hash('sha256', $email);
			$alreadyInvitedEmails[$email] = $actorId;
			$emailsToAdd[] = [
				'email' => $email,
				'actorId' => $actorId,
				'name' => $name,
			];
		}

		if ($testRun) {
			if (empty($invalidLines)) {
				return [
					'invites' => count($emailsToAdd),
					'duplicates' => $duplicates,
				];
			}

			throw new GuestImportException(
				GuestImportException::REASON_ROWS,
				$this->l->t('Following lines are invalid: %s', implode(', ', $invalidLines)),
				$invalidLines,
				count($emailsToAdd),
				$duplicates,
			);
		}

		$data = [
			'invites' => count($emailsToAdd),
			'duplicates' => $duplicates,
		];

		try {
			$this->roomService->setType($room, Room::TYPE_PUBLIC);
			$data['type'] = $room->getType();
		} catch (TypeException) {
		}

		foreach ($emailsToAdd as $add) {
			$participant = $this->participantService->inviteEmailAddress($room, $add['actorId'], $add['email'], $add['name']);
			$this->sendEmailInvitation($room, $participant);
		}

		if (!empty($invalidLines)) {
			$data['invalidLines'] = $invalidLines;
			$data['invalid'] = count($invalidLines);
		}

		return $data;
	}

	public function sendEmailInvitation(Room $room, Participant $participant): void {
		if ($participant->getAttendee()->getActorType() !== Attendee::ACTOR_EMAILS) {
			throw new \InvalidArgumentException('Cannot send email for non-email participant actor type');
		}
		$email = $participant->getAttendee()->getInvitedCloudId();
		$pin = $participant->getAttendee()->getPin();

		$event = new BeforeEmailInvitationSentEvent($room, $participant->getAttendee());
		$this->dispatcher->dispatchTyped($event);

		$link = $this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken(), 'email' => $email, 'access' => $participant->getAttendee()->getAccessToken()]);

		$message = $this->mailer->createMessage();

		$user = $this->userSession->getUser();
		$invitee = $user instanceof IUser ? $user->getDisplayName() : '';

		$template = $this->mailer->createEMailTemplate('Talk.InviteByEmail', [
			'invitee' => $invitee,
			'roomName' => $room->getDisplayName(''),
			'roomLink' => $link,
			'email' => $email,
			'pin' => $pin,
		]);

		if ($user instanceof IUser) {
			$subject = $this->l->t('%1$s invited you to conversation "%2$s".', [$user->getDisplayName(), $room->getDisplayName('')]);
			$message->setFrom([Util::getDefaultEmailAddress('no-reply') => $user->getDisplayName()]);
		} else {
			$subject = $this->l->t('You were invited to conversation "%s".', $room->getDisplayName(''));
			$message->setFrom([Util::getDefaultEmailAddress('no-reply') => $this->defaults->getName()]);
		}

		$template->setSubject($subject);
		$template->addHeader();
		$template->addHeading(
			htmlspecialchars($room->getDisplayName('')),
			$this->l->t('Conversation invitation')
		);
		$template->addBodyText(
			htmlspecialchars($subject),
			$subject
		);

		if ($room->getLobbyState() !== Webinary::LOBBY_NONE && $room->getLobbyTimer() !== null) {
			$timezone = $this->dateTimeZone->getTimeZone();
			$start = $room->getLobbyTimer()->setTimezone($timezone);
			$template->addBodyListItem(
				$this->l->l('datetime', $start) . ' (' . $timezone->getName() . ')',
				$this->l->t('Scheduled time'),
				$this->url->getAbsoluteURL($this->url->imagePath('core', 'places/calendar-dark.png'))
			);
		}

		if (!empty($room->getDescription())) {
			$template->addBodyListItem(
				nl2br(htmlspecialchars($room->getDescription())),
				$this->l->t('Description'),
				$this->url->getAbsoluteURL($this->url->imagePath('core', 'apps/notes.svg')),
				$room->getDescription()
			);
		}

		if ($pin) {
			$template->addBodyText($this->l->t('You can also dial-in via phone with the following details'));

			$template->addBodyListItem(
				$this->talkConfig->getDialInInfo(),
				$this->l->t('Dial-in information'),
				$this->url->getAbsoluteURL($this->url->imagePath('spreed', 'phone.png'))
			);

			$template->addBodyListItem(
				$room->getToken(),
				$this->l->t('Meeting ID'),
				$this->url->getAbsoluteURL($this->url->imagePath('core', 'places/calendar-dark.png'))
			);

			$template->addBodyListItem(
				$pin,
				$this->l->t('Your PIN'),
				$this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/password.png'))
			);
		}

		if ($room->getLobbyState() !== Webinary::LOBBY_NONE && $room->getLobbyTimer() !== null) {
			$template->addBodyText('');
			$template->addBodyText(
				$this->l->t('Click the button below to join the lobby now.'),
				$this->l->t('Click the link below to join the lobby now.')
			);
			$template->addBodyButton(
				$this->l->t('Join lobby for "%s"', [$room->getDisplayName('')]),
				$link
			);
		} else {
			$template->addBodyText('');
			$template->addBodyText(
				$this->l->t('Click the button below to join the conversation now.'),
				$this->l->t('Click the link below to join the conversation now.')
			);
			$template->addBodyButton(
				$this->l->t('Join "%s"', [$room->getDisplayName('')]),
				$link
			);
		}

		$template->addFooter();
		$message->setTo([$email]);
		$message->useTemplate($template);
		try {
			$this->mailer->send($message);

			$event = new EmailInvitationSentEvent($room, $participant->getAttendee());
			$this->dispatcher->dispatchTyped($event);
		} catch (\Exception $e) {
		}
	}
}

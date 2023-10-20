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

namespace OCA\Talk;

use OCA\Talk\Events\AddEmailEvent;
use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\BeforeParticipantModifiedEvent;
use OCA\Talk\Events\ModifyParticipantEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\PollService;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\Util;

class GuestManager {
	public const EVENT_BEFORE_EMAIL_INVITE = self::class . '::preInviteByEmail';
	public const EVENT_AFTER_EMAIL_INVITE = self::class . '::postInviteByEmail';
	/** @deprecated */
	public const EVENT_AFTER_NAME_UPDATE = self::class . '::updateName';

	public function __construct(
		protected Config $talkConfig,
		protected IMailer $mailer,
		protected Defaults $defaults,
		protected IUserSession $userSession,
		protected ParticipantService $participantService,
		protected PollService $pollService,
		protected IURLGenerator $url,
		protected IL10N $l,
		protected IEventDispatcher $dispatcher,
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

			$event = new ModifyParticipantEvent($room, $participant, 'name', $displayName);
			$this->dispatcher->dispatch(self::EVENT_AFTER_NAME_UPDATE, $event);
			$event = new ParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_NAME, $displayName);
			$this->dispatcher->dispatchTyped($event);
		}
	}

	public function sendEmailInvitation(Room $room, Participant $participant): void {
		if ($participant->getAttendee()->getActorType() !== Attendee::ACTOR_EMAILS) {
			throw new \InvalidArgumentException('Cannot send email for non-email participant actor type');
		}
		$email = $participant->getAttendee()->getActorId();
		$pin = $participant->getAttendee()->getPin();

		$event = new AddEmailEvent($room, $email);
		$this->dispatcher->dispatch(self::EVENT_BEFORE_EMAIL_INVITE, $event);

		$link = $this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]);

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
			$subject = $this->l->t('%s invited you to a conversation.', $user->getDisplayName());
			$message->setFrom([Util::getDefaultEmailAddress('no-reply') => $user->getDisplayName()]);
		} else {
			$subject = $this->l->t('You were invited to a conversation.');
			$message->setFrom([Util::getDefaultEmailAddress('no-reply') => $this->defaults->getName()]);
		}

		$template->setSubject($subject);
		$template->addHeader();
		$template->addHeading($this->l->t('Conversation invitation'));
		$template->addBodyText(
			htmlspecialchars($subject . ' ' . $this->l->t('Click the button below to join.')),
			$subject
		);

		$template->addBodyButton(
			$this->l->t('Join »%s«', [$room->getDisplayName('')]),
			$link
		);

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

		$template->addFooter();

		$message->setTo([$email]);
		$message->useTemplate($template);
		try {
			$this->mailer->send($message);

			$this->dispatcher->dispatch(self::EVENT_AFTER_EMAIL_INVITE, $event);
		} catch (\Exception $e) {
		}
	}
}

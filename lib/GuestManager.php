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
use OCA\Talk\Events\ModifyParticipantEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\Util;

class GuestManager {
	public const EVENT_BEFORE_EMAIL_INVITE = self::class . '::preInviteByEmail';
	public const EVENT_AFTER_EMAIL_INVITE = self::class . '::postInviteByEmail';
	public const EVENT_AFTER_NAME_UPDATE = self::class . '::updateName';

	/** @var IDBConnection */
	protected $connection;

	/** @var IMailer */
	protected $mailer;

	/** @var Defaults */
	protected $defaults;

	/** @var IUserSession */
	protected $userSession;

	/** @var IURLGenerator */
	protected $url;

	/** @var IL10N */
	protected $l;

	/** @var IEventDispatcher */
	protected $dispatcher;

	public function __construct(IDBConnection $connection,
								IMailer $mailer,
								Defaults $defaults,
								IUserSession $userSession,
								IURLGenerator $url,
								IL10N $l,
								IEventDispatcher $dispatcher) {
		$this->connection = $connection;
		$this->mailer = $mailer;
		$this->defaults = $defaults;
		$this->userSession = $userSession;
		$this->url = $url;
		$this->l = $l;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * @param Room $room
	 * @param Participant $participant
	 * @param string $displayName
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function updateName(Room $room, Participant $participant, string $displayName): void {
		$sessionHash = $participant->getAttendee()->getActorId();
		$dispatchEvent = true;

		try {
			$oldName = $this->getNameBySessionHash($sessionHash, true);

			if ($oldName !== $displayName) {
				$query = $this->connection->getQueryBuilder();
				$query->update('talk_guests')
					->set('display_name', $query->createNamedParameter($displayName))
					->where($query->expr()->eq('session_hash', $query->createNamedParameter($sessionHash)));
				$query->execute();
			} else {
				$dispatchEvent = false;
			}
		} catch (ParticipantNotFoundException $e) {
			$this->connection->insertIfNotExist('*PREFIX*talk_guests', [
				'session_hash' => $sessionHash,
				'display_name' => $displayName,
			], ['session_hash']);
		}

		if ($dispatchEvent) {
			$event = new ModifyParticipantEvent($room, $participant, 'name', $displayName);
			$this->dispatcher->dispatch(self::EVENT_AFTER_NAME_UPDATE, $event);
		}
	}

	/**
	 * @param string $sessionHash
	 * @param bool $allowEmpty
	 * @return string
	 * @throws ParticipantNotFoundException
	 */
	public function getNameBySessionHash(string $sessionHash, bool $allowEmpty = false): string {
		$query = $this->connection->getQueryBuilder();
		$query->select('display_name')
			->from('talk_guests')
			->where($query->expr()->eq('session_hash', $query->createNamedParameter($sessionHash)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if (isset($row['display_name']) && ($allowEmpty || $row['display_name'] !== '')) {
			return $row['display_name'];
		}

		throw new ParticipantNotFoundException();
	}

	/**
	 * @param string[] $sessionHashes
	 * @return string[]
	 */
	public function getNamesBySessionHashes(array $sessionHashes): array {
		if (empty($sessionHashes)) {
			return [];
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('talk_guests')
			->where($query->expr()->in('session_hash', $query->createNamedParameter($sessionHashes, IQueryBuilder::PARAM_STR_ARRAY)));

		$result = $query->execute();

		$map = [];

		while ($row = $result->fetch()) {
			if ($row['display_name'] === '') {
				continue;
			}

			$map[$row['session_hash']] = $row['display_name'];
		}
		$result->closeCursor();

		return $map;
	}

	public function sendEmailInvitation(Room $room, Participant $participant): void {
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

		if ($pin) {
			// FIXME wrap in text
			$template->addBodyText($pin);
		}

		$template->addBodyButton(
			$this->l->t('Join »%s«', [$room->getDisplayName('')]),
			$link
		);

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

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

namespace OCA\Spreed;


use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Defaults;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\Util;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class GuestManager {

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

	/** @var EventDispatcherInterface */
	protected $dispatcher;

	public function __construct(IDBConnection $connection,
								IMailer $mailer,
								Defaults $defaults,
								IUserSession $userSession,
								IURLGenerator $url,
								IL10N $l,
								EventDispatcherInterface $dispatcher) {
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
	 * @param string $sessionId
	 * @param string $displayName
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function updateName(Room $room, string $sessionId, string $displayName): void {
		$sessionHash = sha1($sessionId);
		$dispatchEvent = true;

		try {
			$oldName = $this->getNameBySessionHash($sessionHash);

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
			$this->dispatcher->dispatch(self::class . '::updateName', new GenericEvent($room, [
				'sessionId' => $sessionId,
				'newName' => $displayName,
			]));
		}
	}

	/**
	 * @param string $sessionHash
	 * @return string
	 * @throws ParticipantNotFoundException
	 */
	public function getNameBySessionHash(string $sessionHash): string {
		$query = $this->connection->getQueryBuilder();
		$query->select('display_name')
			->from('talk_guests')
			->where($query->expr()->eq('session_hash', $query->createNamedParameter($sessionHash)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if (isset($row['display_name']) && $row['display_name'] !== '') {
			return $row['display_name'];
		}

		throw new ParticipantNotFoundException();
	}

	/**
	 * @param string[] $sessionHashes
	 * @return string[]
	 */
	public function getNamesBySessionHashes(array $sessionHashes): array {
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

	public function inviteByEmail(Room $room, string $email): void {
		$this->dispatcher->dispatch(self::class . '::preInviteByEmail', new GenericEvent($room, [
			'email' => $email,
		]));

		$link = $this->url->linkToRouteAbsolute('spreed.pagecontroller.showCall', ['token' => $room->getToken()]);

		$message = $this->mailer->createMessage();

		$user = $this->userSession->getUser();
		$invitee = $user instanceof IUser ? $user->getDisplayName() : '';

		$template = $this->mailer->createEMailTemplate('Talk.InviteByEmail', [
			'invitee' => $invitee,
			'roomName' => $room->getDisplayName(''),
			'roomLink' => $link,
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

		$template->addFooter();

		$message->setTo([$email]);
		$message->useTemplate($template);
		try {
			$this->mailer->send($message);

			$this->dispatcher->dispatch(self::class . '::postInviteByEmail', new GenericEvent($room, [
				'email' => $email,
			]));
		} catch (\Exception $e) {
		}
	}
}

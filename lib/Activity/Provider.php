<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Activity;

use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class Provider implements IProvider {

	/** @var IFactory */
	protected $languageFactory;

	/** @var IURLGenerator */
	protected $url;

	/** @var IManager */
	protected $activityManager;

	/** @var IUserManager */
	protected $userManager;

	/** @var Manager */
	protected $manager;

	/** @var string[] */
	protected $displayNames = [];

	/**
	 * @param IFactory $languageFactory
	 * @param IURLGenerator $url
	 * @param IManager $activityManager
	 * @param IUserManager $userManager
	 * @param Manager $manager
	 */
	public function __construct(IFactory $languageFactory, IURLGenerator $url, IManager $activityManager, IUserManager $userManager, Manager $manager) {
		$this->languageFactory = $languageFactory;
		$this->url = $url;
		$this->activityManager = $activityManager;
		$this->userManager = $userManager;
		$this->manager = $manager;
	}

	/**
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, IEvent $previousEvent = null) {
		if ($event->getApp() !== 'spreed') {
			throw new \InvalidArgumentException();
		}

		$l = $this->languageFactory->get('spreed', $language);

		$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('spreed', 'app.svg')));

		try {
			$parameters = $event->getSubjectParameters();
			$room = $this->manager->getRoomById((int) $parameters['room']);

			if ($room->getName() === '') {
				if ($room->getType() === Room::ONE_TO_ONE_CALL) {
					$parsedParameters = $this->getParameters($parameters);
					$subject = $l->t('{actor} invited you to a private call');
				} else {
					$parsedParameters = $this->getParameters($parameters);
					$subject = $l->t('{actor} invited you to a group call');
				}
			} else {
				$parsedParameters = $this->getParameters($parameters, $room);
				$subject = $l->t('{actor} invited you to the call {call}');
			}
		} catch (RoomNotFoundException $e) {
			throw new \InvalidArgumentException();
		}


		$this->setSubjects($event, $subject, $parsedParameters);

		return $event;
	}

	/**
	 * @param array $parameters
	 * @param Room $room
	 * @return array
	 */
	protected function getParameters(array $parameters, Room $room = null) {
		if ($room === null) {
			return [
				'actor' => $this->getUser($parameters['user']),
			];
		} else {
			return [
				'actor' => $this->getUser($parameters['user']),
				'call' => $this->getRoom($room),
			];
		}
	}

	/**
	 * @param IEvent $event
	 * @param string $subject
	 * @param array $parameters
	 */
	protected function setSubjects(IEvent $event, $subject, array $parameters) {
		$placeholders = $replacements = [];
		foreach ($parameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			$replacements[] = $parameter['name'];
		}

		$event->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $parameters);
	}

	/**
	 * @param Room $room
	 * @return array
	 */
	protected function getRoom(Room $room) {
		switch ($room->getType()) {
			case Room::ONE_TO_ONE_CALL:
				$stringType = 'one2one';
				break;
			case Room::GROUP_CALL:
				$stringType = 'group';
				break;
			case Room::PUBLIC_CALL:
			default:
				$stringType = 'public';
				break;
		}

		return [
			'type' => 'call',
			'id' => $room->getId(),
			'name' => $room->getName(),
			'call-type' => $stringType,
		];
	}

	/**
	 * @param string $uid
	 * @return array
	 */
	protected function getUser($uid) {
		if (!isset($this->displayNames[$uid])) {
			$this->displayNames[$uid] = $this->getDisplayName($uid);
		}

		return [
			'type' => 'user',
			'id' => $uid,
			'name' => $this->displayNames[$uid],
		];
	}

	/**
	 * @param string $uid
	 * @return string
	 */
	protected function getDisplayName($uid) {
		$user = $this->userManager->get($uid);
		if ($user instanceof IUser) {
			return $user->getDisplayName();
		} else {
			return $uid;
		}
	}
}

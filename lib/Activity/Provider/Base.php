<?php

declare(strict_types=1);
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

namespace OCA\Talk\Activity\Provider;

use OCA\Talk\Config;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;

abstract class Base implements IProvider {

	/** @var IFactory */
	protected $languageFactory;
	/** @var IURLGenerator */
	protected $url;
	/** @var Config */
	protected $config;
	/** @var IManager */
	protected $activityManager;
	/** @var IUserManager */
	protected $userManager;
	/** @var Manager */
	protected $manager;

	/** @var string[] */
	protected $displayNames = [];

	public function __construct(IFactory $languageFactory,
								IURLGenerator $url,
								Config $config,
								IManager $activityManager,
								IUserManager $userManager,
								Manager $manager) {
		$this->languageFactory = $languageFactory;
		$this->url = $url;
		$this->config = $config;
		$this->activityManager = $activityManager;
		$this->userManager = $userManager;
		$this->manager = $manager;
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 */
	public function preParse(IEvent $event): IEvent {
		if ($event->getApp() !== 'spreed') {
			throw new \InvalidArgumentException('Wrong app');
		}

		$user = $this->userManager->get($event->getAffectedUser());
		if (!$user instanceof IUser || $this->config->isDisabledForUser($user)) {
			throw new \InvalidArgumentException('User can not user Talk');
		}

		if ($this->activityManager->getRequirePNG()) {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('spreed', 'app-dark.png')));
		} else {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('spreed', 'app-dark.svg')));
		}

		return $event;
	}

	/**
	 * @param IEvent $event
	 * @param string $subject
	 * @param array $parameters
	 * @throws \InvalidArgumentException
	 */
	protected function setSubjects(IEvent $event, string $subject, array $parameters): void {
		$placeholders = $replacements = [];
		foreach ($parameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			$replacements[] = $parameter['name'];
		}

		$event->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $parameters);
	}

	protected function getRoom(Room $room, string $userId): array {
		switch ($room->getType()) {
			case Room::TYPE_ONE_TO_ONE:
				$stringType = 'one2one';
				break;
			case Room::TYPE_GROUP:
				$stringType = 'group';
				break;
			case Room::TYPE_PUBLIC:
			default:
				$stringType = 'public';
				break;
		}

		return [
			'type' => 'call',
			'id' => $room->getId(),
			'name' => $room->getDisplayName($userId),
			'link' => $this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]),
			'call-type' => $stringType,
		];
	}

	protected function getFormerRoom(IL10N $l, int $roomId): array {
		return [
			'type' => 'call',
			'id' => $roomId,
			'name' => $l->t('a conversation'),
			'call-type' => Room::TYPE_UNKNOWN,
		];
	}

	protected function getUser(string $uid): array {
		if (!isset($this->displayNames[$uid])) {
			$this->displayNames[$uid] = $this->getDisplayName($uid);
		}

		return [
			'type' => 'user',
			'id' => $uid,
			'name' => $this->displayNames[$uid],
		];
	}

	protected function getDisplayName(string $uid): string {
		$user = $this->userManager->get($uid);
		if ($user instanceof IUser) {
			return $user->getDisplayName();
		}
		return $uid;
	}
}

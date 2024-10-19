<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Activity\Provider;

use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\Federation\ICloudIdManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;

abstract class Base implements IProvider {

	public function __construct(
		protected IFactory $languageFactory,
		protected IURLGenerator $url,
		protected Config $config,
		protected IManager $activityManager,
		protected IUserManager $userManager,
		protected ICloudIdManager $cloudIdManager,
		protected ParticipantService $participantService,
		protected AvatarService $avatarService,
		protected Manager $manager,
	) {
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws UnknownActivityException
	 */
	public function preParse(IEvent $event): IEvent {
		if ($event->getApp() !== 'spreed') {
			throw new UnknownActivityException('app');
		}

		$uid = $event->getAffectedUser();
		$user = $this->userManager->get($uid);
		if (!$user instanceof IUser || $this->config->isDisabledForUser($user)) {
			throw new UnknownActivityException('User can not use Talk');
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
			case Room::TYPE_ONE_TO_ONE_FORMER:
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
			'id' => (string)$room->getId(),
			'name' => $room->getDisplayName($userId),
			'link' => $this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]),
			'call-type' => $stringType,
			'icon-url' => $this->avatarService->getAvatarUrl($room),
		];
	}

	protected function getFormerRoom(IL10N $l): array {
		return [
			'type' => 'highlight',
			'id' => 'deleted',
			'name' => $l->t('a conversation'),
		];
	}

	protected function getUser(string $uid): array {
		return [
			'type' => 'user',
			'id' => $uid,
			'name' => $this->userManager->getDisplayName($uid) ?? $uid,
		];
	}

	protected function getRemoteUser(Room $room, string $federationId): array {
		$cloudId = $this->cloudIdManager->resolveCloudId($federationId);
		$displayName = $cloudId->getDisplayId();
		try {
			$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_FEDERATED_USERS, $federationId);
			$displayName = $participant->getAttendee()->getDisplayName();
		} catch (ParticipantNotFoundException) {
		}

		return [
			'type' => 'user',
			'id' => $cloudId->getUser(),
			'name' => $displayName,
			'server' => $cloudId->getRemote(),
		];
	}
}

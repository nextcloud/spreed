<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Notification;


use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\RichObjectStrings\Definitions;
use OCP\RichObjectStrings\InvalidObjectExeption;

class Notifier implements INotifier {

	/** @var IFactory */
	protected $lFactory;

	/** @var IURLGenerator */
	protected $url;

	/** @var IUserManager */
	protected $userManager;

	/** @var Manager */
	protected $manager;

	/** @var Definitions */
	protected $definitions;

	/**
	 * @param IFactory $lFactory
	 * @param IURLGenerator $url
	 * @param IUserManager $userManager
	 * @param Manager $manager
	 * @param Definitions $definitions
	 */
	public function __construct(IFactory $lFactory, IURLGenerator $url, IUserManager $userManager, Manager $manager, Definitions $definitions) {
		$this->lFactory = $lFactory;
		$this->url = $url;
		$this->userManager = $userManager;
		$this->manager = $manager;
		$this->definitions = $definitions;
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, $languageCode) {
		if ($notification->getApp() !== 'spreed') {
			throw new \InvalidArgumentException('Incorrect app');
		}

		$l = $this->lFactory->get('spreed', $languageCode);

		try {
			$room = $this->manager->getRoomById((int) $notification->getObjectId());
		} catch (RoomNotFoundException $e) {
			// Room does not exist
			throw new \InvalidArgumentException('Invalid room');
		}

		if ($notification->getSubject() === 'invitation') {
			$parameters = $notification->getSubjectParameters();
			$uid = $parameters[0];
			$notification
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('spreed', 'app.svg')))
				->setLink($this->url->linkToRouteAbsolute('spreed.page.index') . '?token=' . $room->getToken());

			if ($notification->getObjectType() === 'room') {
				$user = $this->userManager->get($uid);
				if ($user instanceof IUser) {
					if ($room->getType() === Room::ONE_TO_ONE_CALL) {
						$notification
							->setParsedSubject(
								$l->t('%s invited you to a private call', [$user->getDisplayName()])
							)
							->setRichSubject(
								$l->t('{user} invited you to a private call'), [
									'user' => [
										'type' => 'user',
										'id' => $uid,
										'name' => $user->getDisplayName(),
									]
								]
							)
						;
					} else if ($room->getType() === Room::GROUP_CALL ||
						$room->getType() === Room::PUBLIC_CALL) {
						$callTypeIsSupported = false;
						try {
							$this->definitions->getDefinition('call');
							$callTypeIsSupported = true;
						} catch (InvalidObjectExeption $e) {
							// Before 11.0.2 this is not supported
						}

						if ($callTypeIsSupported && $room->getName() !== '') {
							$notification
								->setParsedSubject(
									$l->t('%s invited you to a group call: %s', [$user->getDisplayName(), $room->getName()])
								)
								->setRichSubject(
									$l->t('{user} invited you to a group call: {call}'), [
										'user' => [
											'type' => 'user',
											'id' => $uid,
											'name' => $user->getDisplayName(),
										],
										'call' => [
											'type' => 'call',
											'id' => $room->getId(),
											'name' => $room->getName(),
											'call-type' => $this->getRoomType($room),
										],
									]
								)
							;
						} else {
							$notification
								->setParsedSubject(
									$l->t('%s invited you to a group call', [$user->getDisplayName()])
								)
								->setRichSubject(
									$l->t('{user} invited you to a group call'), [
										'user' => [
											'type' => 'user',
											'id' => $uid,
											'name' => $user->getDisplayName(),
										]
									]
								)
							;
						}
					} else {
						throw new \InvalidArgumentException('Unknown room type');
					}
				} else {
					throw new \InvalidArgumentException('Calling user does not exist anymore');
				}
			} else {
				throw new \InvalidArgumentException('Unknown object type');
			}
		} else {
			throw new \InvalidArgumentException('Unknown subject');
		}

		return $notification;
	}

	/**
	 * @param Room $room
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	protected function getRoomType(Room $room) {
		switch ($room->getType()) {
			case Room::ONE_TO_ONE_CALL:
				return 'one2one';
			case Room::GROUP_CALL:
				return 'group';
			case Room::PUBLIC_CALL:
				return 'public';
			default:
				throw new \InvalidArgumentException('Unknown room type');
		}
	}
}

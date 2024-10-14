<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Share\Helper;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Share\IShare;

/**
 * Helper of OCA\Files_Sharing\Controller\ShareAPIController for room shares.
 *
 * The methods of this class are called from the ShareAPIController to perform
 * actions or checks specific to room shares.
 */
class ShareAPIController {

	public function __construct(
		protected string $userId,
		protected Manager $manager,
		protected ParticipantService $participantService,
		protected ITimeFactory $timeFactory,
		protected IL10N $l,
		protected IURLGenerator $urlGenerator,
	) {
	}

	/**
	 * Formats the specific fields of a room share for OCS output.
	 *
	 * The returned fields override those set by the main ShareAPIController.
	 *
	 * @param IShare $share
	 * @return array
	 */
	public function formatShare(IShare $share): array {
		$result = [];

		try {
			$room = $this->manager->getRoomByToken($share->getSharedWith(), $this->userId);
		} catch (RoomNotFoundException $e) {
			return $result;
		}

		$result['share_with_displayname'] = $room->getDisplayName($this->userId);
		try {
			$this->participantService->getParticipant($room, $this->userId, false);
			$result['share_with_link'] = $this->urlGenerator->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]);
		} catch (ParticipantNotFoundException $e) {
			// Removing the conversation token from the leaked data if not a participant.
			// Adding some unique but reproducable part to the share_with here
			// so the avatars for conversations are distinguishable
			$result['share_with'] = 'private_conversation_' . substr(sha1($room->getName() . $room->getId()), 0, 6);
			$result['share_with_link'] = '';
		}
		if ($room->getType() === Room::TYPE_PUBLIC) {
			$result['token'] = $share->getToken();
		}

		return $result;
	}

	/**
	 * Prepares the given share to be passed to OC\Share20\Manager::createShare.
	 *
	 * @param IShare $share
	 * @param string $shareWith
	 * @param int $permissions
	 * @param string $expireDate
	 * @throws OCSNotFoundException
	 */
	public function createShare(IShare $share, string $shareWith, int $permissions, string $expireDate): void {
		$share->setSharedWith($shareWith);
		$share->setPermissions($permissions);

		if ($expireDate !== '') {
			try {
				$expireDateTime = $this->parseDate($expireDate);
				$share->setExpirationDate($expireDateTime);
			} catch (\Exception $e) {
				throw new OCSNotFoundException($this->l->t('Invalid date, date format must be YYYY-MM-DD'));
			}
		}
	}

	/**
	 * Make sure that the passed date is valid ISO 8601
	 * So YYYY-MM-DD
	 * If not throw an exception
	 *
	 * Copied from \OCA\Files_Sharing\Controller\ShareAPIController::parseDate.
	 *
	 * @param string $expireDate
	 * @return \DateTime
	 * @throws \Exception
	 */
	private function parseDate(string $expireDate): \DateTime {
		try {
			$date = $this->timeFactory->getDateTime($expireDate);
		} catch (\Exception $e) {
			throw new \Exception('Invalid date. Format must be YYYY-MM-DD');
		}

		$date->setTime(0, 0);

		return $date;
	}

	/**
	 * Returns whether the given user can access the given room share or not.
	 *
	 * A user can access a room share only if they are a participant of the room.
	 *
	 * @param IShare $share
	 * @param string $user
	 * @return bool
	 */
	public function canAccessShare(IShare $share, string $user): bool {
		try {
			$room = $this->manager->getRoomByToken($share->getSharedWith(), $user);
		} catch (RoomNotFoundException $e) {
			return false;
		}

		try {
			$this->participantService->getParticipant($room, $user, false);
		} catch (ParticipantNotFoundException $e) {
			return false;
		}

		return true;
	}
}

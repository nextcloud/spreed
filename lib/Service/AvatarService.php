<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022, Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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

namespace OCA\Talk\Service;

use InvalidArgumentException;
use OCA\Talk\Room;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\InMemoryFile;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IAvatarManager;
use OCP\IL10N;
use OCP\IUser;

class AvatarService {
	private IAppData $appData;
	private IL10N $l;
	private IAvatarManager $avatarManager;

	public function __construct(
		IAppData $appData,
		IL10N $l,
		IAvatarManager $avatarManager
	) {
		$this->appData = $appData;
		$this->l = $l;
		$this->avatarManager = $avatarManager;
	}

	public function setAvatar(Room $room, string $content): void {
		$image = new \OC_Image();
		$image->loadFromData($content);
		$image->readExif($content);
		$image->fixOrientation();
		if (!($image->height() === $image->width())) {
			throw new InvalidArgumentException($this->l->t('Avatar image is not square'));
		}

		if (!$image->valid()) {
			throw new InvalidArgumentException($this->l->t('Invalid image'));
		}

		$mimeType = $image->mimeType();
		$allowedMimeTypes = [
			'image/jpeg',
			'image/png',
			'image/svg',
		];
		if (!in_array($mimeType, $allowedMimeTypes)) {
			throw new InvalidArgumentException($this->l->t('Unknown filetype'));
		}

		try {
			$folder = $this->appData->getFolder('room-avatar');
		} catch (NotFoundException $e) {
			$folder = $this->appData->newFolder('room-avatar');
		}
		$token = $room->getToken();
		$folder->newFile($token, $image->data());
	}

	public function getAvatar(Room $room, ?IUser $user, bool $dark = false): ISimpleFile {
		try {
			$folder = $this->appData->getFolder('room-avatar');
		} catch (NotFoundException $e) {
			$folder = $this->appData->newFolder('room-avatar');
		}
		$token = $room->getToken();
		if ($folder->fileExists($token . '.png')) {
			$file = $folder->getFile($token . '.png');
		} elseif ($folder->fileExists($token . '.jpeg')) {
			$file = $folder->getFile($token . '.jpeg');
		} else {
			if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
				$users = json_decode($room->getName(), true);
				foreach ($users as $participantId) {
					if ($participantId !== $user->getUID()) {
						$avatar = $this->avatarManager->getAvatar($participantId);
						$file = $avatar->getFile(512);
					}
				}
			} elseif ($room->getObjectType() === 'file') {
				$file = new InMemoryFile($token, file_get_contents(__DIR__ . '/../../img/icon-file-white.svg'));
			} elseif ($room->getObjectType() === 'share:password') {
				$file = new InMemoryFile($token, file_get_contents(__DIR__ . '/../../img/icon-password-white.svg'));
			} elseif ($room->getObjectType() === 'emails') {
				$file = new InMemoryFile($token, file_get_contents(__DIR__ . '/../../img/icon-mail-white.svg'));
			} elseif ($room->getType() === Room::TYPE_PUBLIC) {
				$file = new InMemoryFile($token, file_get_contents(__DIR__ . '/../../img/icon-public-white.svg'));
			} else {
				$file = new InMemoryFile($token, file_get_contents(__DIR__ . '/../../img/icon-contacts-white.svg'));
			}
		}
		return $file;
	}

	public function deleteAvatar(Room $room): void {
		try {
			$folder = $this->appData->getFolder('room-avatar');
		} catch (NotFoundException $e) {
			$folder = $this->appData->newFolder('room-avatar');
		}
		$token = $room->getToken();
		$folder->delete($token);
	}
}

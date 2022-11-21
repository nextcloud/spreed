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
use OC\Files\Filesystem;
use OCA\Talk\Room;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\InMemoryFile;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IAvatarManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Security\ISecureRandom;

class AvatarService {
	private IAppData $appData;
	private IL10N $l;
	private IConfig $config;
	private IURLGenerator $url;
	private ISecureRandom $random;
	private RoomService $roomService;
	private IAvatarManager $avatarManager;

	public function __construct(
		IAppData $appData,
		IL10N $l,
		IConfig $config,
		IURLGenerator $url,
		ISecureRandom $random,
		RoomService $roomService,
		IAvatarManager $avatarManager
	) {
		$this->appData = $appData;
		$this->l = $l;
		$this->config = $config;
		$this->url = $url;
		$this->random = $random;
		$this->roomService = $roomService;
		$this->avatarManager = $avatarManager;
	}

	public function setAvatarFromRequest(Room $room, ?array $file): void {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			throw new InvalidArgumentException($this->l->t('One to one rooms always need to show the other users avatar'));
		}

		if (is_null($file) || !is_array($file)) {
			throw new InvalidArgumentException($this->l->t('No image file provided'));
		}

		if (
			$file['error'] !== 0 ||
			!is_uploaded_file($file['tmp_name']) ||
			Filesystem::isFileBlacklisted($file['tmp_name'])
		) {
			throw new InvalidArgumentException($this->l->t('Invalid file provided'));
		}
		if ($file['size'] > 20 * 1024 * 1024) {
			throw new InvalidArgumentException($this->l->t('File is too big'));
		}

		$content = file_get_contents($file['tmp_name']);
		unlink($file['tmp_name']);
		$image = new \OC_Image();
		$image->loadFromData($content);
		$image->readExif($content);
		$this->setAvatar($room, $image);
	}

	public function setAvatar(Room $room, \OC_Image $image): void {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			throw new InvalidArgumentException($this->l->t('One to one rooms always need to show the other users avatar'));
		}
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
		];
		if (!in_array($mimeType, $allowedMimeTypes)) {
			throw new InvalidArgumentException($this->l->t('Unknown filetype'));
		}

		$token = $room->getToken();
		$avatarFolder = $this->getAvatarFolder($token);
		$avatarName = $this->getRandomAvatarName($room);
		$avatarFolder->newFile($avatarName, $image->data());
		$room->setAvatar($avatarName);
		$this->roomService->setAvatar($room, $avatarName);
	}

	private function getRandomAvatarName(Room $room): string {
		$name = $this->random->generate(10, ISecureRandom::CHAR_HUMAN_READABLE);
		if ($name === $room->getName()) {
			return $this->getRandomAvatarName($room);
		}
		return $name;
	}

	private function getAvatarFolder(string $token): ISimpleFolder {
		try {
			$folder = $this->appData->getFolder('room-avatar');
		} catch (NotFoundException $e) {
			$folder = $this->appData->newFolder('room-avatar');
		}
		try {
			$avatarFolder = $folder->getFolder($token);
		} catch (NotFoundException $e) {
			$avatarFolder = $folder->newFolder($token);
		}
		return $avatarFolder;
	}

	public function getAvatar(Room $room, ?IUser $user, bool $darkTheme = false): ISimpleFile {
		$token = $room->getToken();
		$avatar = $room->getAvatar();
		if ($avatar) {
			try {
				$folder = $this->appData->getFolder('room-avatar');
				if ($folder->fileExists($token)) {
					$file = $folder->getFolder($token)->getFile($avatar);
				}
			} catch (NotFoundException $e) {
			}
		}
		// Fallback
		if (!isset($file)) {
			if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
				$users = json_decode($room->getName(), true);
				foreach ($users as $participantId) {
					if ($participantId !== $user->getUID()) {
						$avatar = $this->avatarManager->getAvatar($participantId);
						$file = $avatar->getFile(512, $darkTheme);
					}
				}
			} elseif ($room->getObjectType() === 'file') {
				$file = new InMemoryFile($token, file_get_contents(__DIR__ . '/../../img/icon-text-white.svg'));
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
			$token = $room->getToken();
			$avatarFolder = $this->getAvatarFolder($token);
			$avatarFolder->delete();
			$this->roomService->setAvatar($room, null);
		} catch (NotFoundException $e) {
		}
	}

	public function roomHasAvatar(Room $room): bool {
		try {
			$folder = $this->appData->getFolder('room-avatar');
			if ($folder->fileExists($room->getToken())) {
				return true;
			}
		} catch (NotFoundException $e) {
		}
		return $room->getType() === Room::TYPE_ONE_TO_ONE;
	}

	public function getAvatarVersion(Room $room, ?string $userId): string {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE && $userId) {
			return (string) $this->config->getUserValue($userId, 'avatar', 'version', '0');
		}
		return (string) ($this->cache->get($room->getToken() . '.avatarVersion') ?? 0);
	}

	public function getAvatarUrl(Room $room): string {
		$arguments = [
			'token' => $room->getToken(),
			'apiVersion' => 'v1',
		];
		if ($avatar = $room->getAvatar()) {
			$arguments['v'] = $room->getAvatar();
		}
		return $this->url->linkToRouteAbsolute('ocs.spreed.Avatar.getAvatar', $arguments);
	}
}

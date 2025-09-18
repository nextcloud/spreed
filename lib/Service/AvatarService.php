<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Security\ISecureRandom;

class AvatarService {
	public const THEMING_PLACEHOLDER = '{{THEMING}}';
	public const THEMING_DARK_BACKGROUND = '3B3B3B';
	public const THEMING_BRIGHT_BACKGROUND = '6B6B6B';

	public function __construct(
		private IAppData $appData,
		private IL10N $l,
		private IURLGenerator $url,
		private ISecureRandom $random,
		private RoomService $roomService,
		private IAvatarManager $avatarManager,
		private EmojiService $emojiService,
	) {
	}

	public function setAvatarFromRequest(Room $room, ?array $file): void {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			throw new InvalidArgumentException($this->l->t('One-to-one rooms always need to show the other users avatar'));
		}

		if ($file === null) {
			throw new InvalidArgumentException($this->l->t('No image file provided'));
		}

		if (
			$file['error'] !== 0
			|| !is_uploaded_file($file['tmp_name'])
			|| Filesystem::isFileBlacklisted($file['tmp_name'])
		) {
			throw new InvalidArgumentException($this->l->t('Invalid file provided'));
		}
		if ($file['size'] > 20 * 1024 * 1024) {
			throw new InvalidArgumentException($this->l->t('File is too big'));
		}

		$content = file_get_contents($file['tmp_name']);
		// noopengrep: php.lang.security.unlink-use.unlink-use
		unlink($file['tmp_name']);
		$image = new \OCP\Image();
		$image->loadFromData($content);
		$image->readExif($content);
		$this->setAvatar($room, $image);
	}

	public function setAvatarFromEmoji(Room $room, string $emoji, ?string $color): void {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			throw new InvalidArgumentException($this->l->t('One-to-one rooms always need to show the other users avatar'));
		}

		if ($this->emojiService->getFirstCombinedEmoji($emoji) !== $emoji) {
			throw new InvalidArgumentException($this->l->t('Invalid emoji character'));
		}

		if ($color === null) {
			$color = self::THEMING_PLACEHOLDER;
		} elseif (!preg_match('/^[a-fA-F0-9]{6}$/', $color)) {
			throw new InvalidArgumentException($this->l->t('Invalid background color'));
		}

		$content = $this->getEmojiAvatar($emoji, $color);

		$token = $room->getToken();
		$avatarFolder = $this->getAvatarFolder($token);

		// Delete previous avatars
		foreach ($avatarFolder->getDirectoryListing() as $file) {
			$file->delete();
		}

		$avatarName = $this->random->generate(16, ISecureRandom::CHAR_HUMAN_READABLE) . '.svg';
		$avatarFolder->newFile($avatarName, $content);
		$this->roomService->setAvatar($room, $avatarName);
	}

	public function setAvatar(Room $room, \OCP\Image $image): void {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			throw new InvalidArgumentException($this->l->t('One-to-one rooms always need to show the other users avatar'));
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

		// Delete previous avatars
		foreach ($avatarFolder->getDirectoryListing() as $file) {
			$file->delete();
		}

		$avatarName = $this->random->generate(16, ISecureRandom::CHAR_HUMAN_READABLE);
		if ($mimeType === 'image/jpeg') {
			$avatarName .= '.jpg';
		} else {
			$avatarName .= '.png';
		}

		$avatarFolder->newFile($avatarName, $image->data());
		$this->roomService->setAvatar($room, $avatarName);
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

	/**
	 * https://github.com/sebdesign/cap-height -- for 500px height
	 * Automated check: https://codepen.io/skjnldsv/pen/PydLBK/
	 * Noto Sans cap-height is 0.715 and we want a 200px caps height size
	 * (0.4 letter-to-total-height ratio, 500*0.4=200), so: 200/0.715 = 280px.
	 * Since we start from the baseline (text-anchor) we need to
	 * shift the y axis by 100px (half the caps height): 500/2+100=350
	 *
	 * Copied from @see \OC\Avatar\Avatar::$svgTemplate with some changes:
	 * - {font} is injected
	 * - size fixed to 512
	 * - font-size reduced to 240
	 * - font-weight and fill color are removed as they are not applicable
	 */
	private string $svgTemplate = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
		<svg width="512" height="512" version="1.1" viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg">
			<rect width="100%" height="100%" fill="#{fill}"></rect>
			<text x="50%" y="330" style="font-size:240px;font-family:{font};text-anchor:middle;">{letter}</text>
		</svg>';

	public function getAvatar(Room $room, ?IUser $user, bool $darkTheme = false): ISimpleFile {
		$token = $room->getToken();
		$avatar = $room->getAvatar();
		if ($avatar) {
			try {
				$folder = $this->appData->getFolder('room-avatar');
				if ($folder->fileExists($token)) {
					$file = $folder->getFolder($token)->getFile($avatar);

					if ($file->getMimeType() === 'image/svg+xml' && str_contains($file->getContent(), self::THEMING_PLACEHOLDER)) {
						$color = $darkTheme ? self::THEMING_DARK_BACKGROUND : self::THEMING_BRIGHT_BACKGROUND;
						return new InMemoryFile(
							$file->getName(),
							str_replace(self::THEMING_PLACEHOLDER, $color, $file->getContent()),
						);
					}

					return $file;
				}
			} catch (NotFoundException $e) {
			}
		}

		// Fallback
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			$users = json_decode($room->getName(), true);
			foreach ($users as $participantId) {
				if ($user instanceof IUser && $participantId !== $user->getUID()) {
					$avatar = $this->avatarManager->getAvatar($participantId);
					return $avatar->getFile(512, $darkTheme);
				}
			}
		}
		if ($this->emojiService->isValidSingleEmoji(mb_substr($room->getName(), 0, 1))) {
			return new InMemoryFile(
				$token,
				$this->getEmojiAvatar(
					$this->emojiService->getFirstCombinedEmoji($room->getName()),
					$darkTheme ? self::THEMING_DARK_BACKGROUND : self::THEMING_BRIGHT_BACKGROUND
				)
			);
		}
		return new InMemoryFile($token, file_get_contents($this->getAvatarPath($room, $darkTheme)));
	}

	public function getPersonPlaceholder(bool $darkTheme = false): ISimpleFile {
		$colorTone = $darkTheme ? 'dark' : 'bright';
		return new InMemoryFile('fallback', file_get_contents(__DIR__ . '/../../img/icon-conversation-user-' . $colorTone . '.svg'));
	}

	protected function getEmojiAvatar(string $emoji, string $fillColor): string {
		return str_replace([
			'{letter}',
			'{fill}',
			'{font}',
		], [
			$emoji,
			$fillColor,
			implode(',', [
				"'Segoe UI'",
				'Roboto',
				'Oxygen-Sans',
				'Cantarell',
				'Ubuntu',
				"'Helvetica Neue'",
				'Arial',
				'sans-serif',
				"'Noto Color Emoji'",
				"'Apple Color Emoji'",
				"'Segoe UI Emoji'",
				"'Segoe UI Symbol'",
				"'Noto Sans'",
			]),
		], $this->svgTemplate);
	}

	public function isCustomAvatar(Room $room): bool {
		return $room->getAvatar() !== '';
	}

	private function getAvatarPath(Room $room, bool $darkTheme = false): string {
		$colorTone = $darkTheme ? 'dark' : 'bright';
		if ($room->getType() === Room::TYPE_CHANGELOG) {
			return __DIR__ . '/../../img/changelog.svg';
		}
		if ($room->getObjectType() === Room::OBJECT_TYPE_FILE) {
			return __DIR__ . '/../../img/icon-conversation-text-' . $colorTone . '.svg';
		}
		if ($room->getObjectType() === Room::OBJECT_TYPE_VIDEO_VERIFICATION) {
			return __DIR__ . '/../../img/icon-conversation-password-' . $colorTone . '.svg';
		}
		if ($room->getObjectType() === Room::OBJECT_TYPE_EMAIL) {
			return __DIR__ . '/../../img/icon-conversation-mail-' . $colorTone . '.svg';
		}
		if (in_array($room->getObjectType(), [Room::OBJECT_TYPE_PHONE_PERSIST, Room::OBJECT_TYPE_PHONE_TEMPORARY, Room::OBJECT_TYPE_PHONE_LEGACY], true)) {
			return __DIR__ . '/../../img/icon-conversation-phone-' . $colorTone . '.svg';
		}
		if ($room->getObjectType() === Room::OBJECT_TYPE_EVENT) {
			return __DIR__ . '/../../img/icon-conversation-event-' . $colorTone . '.svg';
		}
		if ($room->isFederatedConversation()) {
			return __DIR__ . '/../../img/icon-conversation-federation-' . $colorTone . '.svg';
		}
		if ($room->getType() === Room::TYPE_PUBLIC) {
			return __DIR__ . '/../../img/icon-conversation-public-' . $colorTone . '.svg';
		}
		if ($room->getType() === Room::TYPE_ONE_TO_ONE_FORMER
			|| $room->getType() === Room::TYPE_ONE_TO_ONE
		) {
			return __DIR__ . '/../../img/icon-conversation-user-' . $colorTone . '.svg';
		}
		return __DIR__ . '/../../img/icon-conversation-group-' . $colorTone . '.svg';
	}

	public function deleteAvatar(Room $room): void {
		try {
			$folder = $this->appData->getFolder('room-avatar');
			$avatarFolder = $folder->getFolder($room->getToken());
			$avatarFolder->delete();
			$this->roomService->setAvatar($room, '');
		} catch (NotFoundException $e) {
		}
	}

	public function getAvatarUrl(Room $room): string {
		$arguments = [
			'token' => $room->getToken(),
			'apiVersion' => 'v1',
		];

		$avatarVersion = $this->getAvatarVersion($room);
		if ($avatarVersion !== '') {
			$arguments['v'] = $avatarVersion;
		}
		return $this->url->linkToOCSRouteAbsolute('spreed.Avatar.getAvatar', $arguments);
	}

	public function getAvatarVersion(Room $room): string {
		$avatarVersion = $room->getAvatar();
		if ($avatarVersion) {
			[$version] = explode('.', $avatarVersion);
			return $version;
		}
		if ($this->emojiService->isValidSingleEmoji(mb_substr($room->getName(), 0, 1))) {
			return substr(md5($this->getEmojiAvatar($this->emojiService->getFirstCombinedEmoji($room->getName()), self::THEMING_BRIGHT_BACKGROUND)), 0, 8);
		}
		$avatarPath = $this->getAvatarPath($room);
		return substr(md5($avatarPath), 0, 8);
	}
}

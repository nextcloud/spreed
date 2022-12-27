<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Vitor Mattos <vitor@php.rio>
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
use OC\User\NoUserException;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Room;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

class RecordingService {
	private IMimeTypeDetector $mimeTypeDetector;
	private ParticipantService $participantService;
	private IRootFolder $rootFolder;
	private Config $config;

	public function __construct(
		IMimeTypeDetector $mimeTypeDetector,
		ParticipantService $participantService,
		IRootFolder $rootFolder,
		Config $config
	) {
		$this->mimeTypeDetector = $mimeTypeDetector;
		$this->participantService = $participantService;
		$this->rootFolder = $rootFolder;
		$this->config = $config;
	}

	public function store(Room $room, string $owner, array $file): void {
		$content = $this->getContentFromFileArray($file['tmp_name']);

		$recordFileName = $this->sanitizeFileName($file['name']);
		$this->validateFileFormat($recordFileName, $content);

		try {
			$this->participantService->getParticipant($room, $owner);
		} catch (ParticipantNotFoundException $e) {
			throw new InvalidArgumentException('owner_participant');
		}

		try {
			$recordingFolder = $this->getRecordingFolder($owner, $room->getToken());
			$recordingFolder->newFile($recordFileName, $content);
		} catch (NoUserException $e) {
			throw new InvalidArgumentException('owner_invalid');
		} catch (NotPermittedException $e) {
			throw new InvalidArgumentException('owner_permission');
		}
	}

	public function getContentFromFileArray(array $file): string {
		if (
			$file['error'] !== 0 ||
			!is_uploaded_file($file['tmp_name']) ||
			Filesystem::isFileBlacklisted($file['tmp_name'])
		) {
			throw new InvalidArgumentException('invalid_file');
		}

		$content = file_get_contents($file['tmp_name']);
		unlink($file['tmp_name']);

		if (!$content) {
			throw new InvalidArgumentException('empty_file');
		}
		return $content;
	}

	public function validateFileFormat(string $fileName, $content): void {
		$mimeType = $this->mimeTypeDetector->detectString($content);
		$allowedMimeTypes = [
			'video/mp4',
			'video/mpeg',
			'video/ogg',
			'audio/mp3',
			'audio/ogg',
		];
		if (!in_array($mimeType, $allowedMimeTypes)) {
			throw new InvalidArgumentException('file_mimetype');
		}

		$extensionFromMime = pathinfo(str_replace('/', '.', $mimeType), PATHINFO_EXTENSION);
		$extensionFromFileName = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		if ($extensionFromFileName !== $extensionFromMime) {
			throw new InvalidArgumentException('file_extension');
		}
	}

	public function sanitizeFileName(string $fileName): string {
		$recordFileName = escapeshellcmd($fileName);
		$recordFileName = pathinfo($recordFileName, PATHINFO_BASENAME);
		if ($recordFileName !== $fileName) {
			throw new InvalidArgumentException('file_name');
		}
		return $recordFileName;
	}

	private function getRecordingFolder(string $owner, string $token): Folder {
		$attachmentFolderName = $this->config->getAttachmentFolder($owner);

		$userFolder = $this->rootFolder->getUserFolder($owner);
		try {
			/** @var \OCP\Files\Folder */
			$attachmentFolder = $userFolder->get($attachmentFolderName);
		} catch (NotFoundException $e) {
			$attachmentFolder = $userFolder->newFolder($attachmentFolderName);
		}
		$recordingRootFolderName = $this->config->getRecordingFolder($owner);
		try {
			/** @var \OCP\Files\Folder */
			$recordingRootFolder = $attachmentFolder->get($recordingRootFolderName);
		} catch (NotFoundException $e) {
			/** @var \OCP\Files\Folder */
			$recordingRootFolder = $attachmentFolder->newFolder($recordingRootFolderName);
		}
		try {
			$recordingFolder = $recordingRootFolder->get($token);
		} catch (NotFoundException $e) {
			$recordingFolder = $recordingRootFolder->newFolder($token);
		}
		return $recordingFolder;
	}
}

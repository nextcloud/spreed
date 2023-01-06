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
	public const DEFAULT_ALLOWED_RECORDING_FORMATS = [
		'audio/ogg' => ['ogg'],
		'video/ogg' => ['ogv'],
		'video/x-matroska' => ['mkv'],
	];

	public function __construct(
		private IMimeTypeDetector $mimeTypeDetector,
		private ParticipantService $participantService,
		private IRootFolder $rootFolder,
		private Config $config,
		private RoomService $roomService
	) {
	}

	public function start(Room $room, int $status): void {
		$availableRecordingTypes = [Room::RECORDING_VIDEO, Room::RECORDING_AUDIO];
		if (!in_array($status, $availableRecordingTypes)) {
			throw new InvalidArgumentException('status');
		}
		if ($room->getCallRecording() !== Room::RECORDING_NONE) {
			throw new InvalidArgumentException('recording');
		}
		if (!$room->getActiveSince() instanceof \DateTimeInterface) {
			throw new InvalidArgumentException('call');
		}
		$this->roomService->setCallRecording($room, $status);
	}

	public function stop(Room $room): void {
		if ($room->getCallRecording() === Room::RECORDING_NONE) {
			throw new InvalidArgumentException('recording');
		}
		$this->roomService->setCallRecording($room);
	}

	public function store(Room $room, string $owner, array $file): void {
		$content = $this->getContentFromFileArray($file);

		$fileName = basename($file['name']);
		$this->validateFileFormat($fileName, $content);

		try {
			$this->participantService->getParticipant($room, $owner);
		} catch (ParticipantNotFoundException $e) {
			throw new InvalidArgumentException('owner_participant');
		}

		try {
			$recordingFolder = $this->getRecordingFolder($owner, $room->getToken());
			$recordingFolder->newFile($fileName, $content);
		} catch (NoUserException $e) {
			throw new InvalidArgumentException('owner_invalid');
		} catch (NotPermittedException $e) {
			throw new InvalidArgumentException('owner_permission');
		}
	}

	public function getContentFromFileArray(array $file): string {
		if (
			$file['error'] !== 0 ||
			!is_uploaded_file($file['tmp_name'])
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
		$allowed = self::DEFAULT_ALLOWED_RECORDING_FORMATS;
		if (!array_key_exists($mimeType, $allowed)) {
			throw new InvalidArgumentException('file_mimetype');
		}

		$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		if (!$extension || !in_array($extension, $allowed[$mimeType])) {
			throw new InvalidArgumentException('file_extension');
		}
	}

	private function getRecordingFolder(string $owner, string $token): Folder {
		$userFolder = $this->rootFolder->getUserFolder($owner);
		$recordingRootFolderName = $this->config->getRecordingFolder($owner);
		try {
			/** @var \OCP\Files\Folder */
			$recordingRootFolder = $userFolder->get($recordingRootFolderName);
		} catch (NotFoundException $e) {
			/** @var \OCP\Files\Folder */
			$recordingRootFolder = $userFolder->newFolder($recordingRootFolderName);
		}
		try {
			$recordingFolder = $recordingRootFolder->get($token);
		} catch (NotFoundException $e) {
			$recordingFolder = $recordingRootFolder->newFolder($token);
		}
		return $recordingFolder;
	}
}

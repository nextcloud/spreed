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
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Recording\BackendNotifier;
use OCA\Talk\Room;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Notification\IManager;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class RecordingService {
	public const DEFAULT_ALLOWED_RECORDING_FORMATS = [
		'audio/ogg' => ['ogg'],
		'video/ogg' => ['ogv'],
		'video/webm' => ['webm'],
		'video/x-matroska' => ['mkv'],
	];

	public function __construct(
		protected IMimeTypeDetector $mimeTypeDetector,
		protected ParticipantService $participantService,
		protected IRootFolder $rootFolder,
		protected IManager $notificationManager,
		protected Manager $roomManager,
		protected ITimeFactory $timeFactory,
		protected Config $config,
		protected RoomService $roomService,
		protected ShareManager $shareManager,
		protected ChatManager $chatManager,
		protected LoggerInterface $logger,
		protected BackendNotifier $backendNotifier
	) {
	}

	public function start(Room $room, int $status, string $owner, Participant $participant): void {
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
		if (!$this->config->isRecordingEnabled()) {
			throw new InvalidArgumentException('config');
		}

		$this->backendNotifier->start($room, $status, $owner, $participant);
	}

	public function stop(Room $room, ?Participant $participant = null): void {
		if ($room->getCallRecording() === Room::RECORDING_NONE) {
			return;
		}

		$this->backendNotifier->stop($room, $participant);
	}

	public function store(Room $room, string $owner, array $file): void {
		$content = $this->getContentFromFileArray($file);

		$fileName = basename($file['name']);
		$this->validateFileFormat($fileName, $content);

		try {
			$participant = $this->participantService->getParticipant($room, $owner);
		} catch (ParticipantNotFoundException $e) {
			throw new InvalidArgumentException('owner_participant');
		}

		try {
			$recordingFolder = $this->getRecordingFolder($owner, $room->getToken());
			$file = $recordingFolder->newFile($fileName, $content);
			$this->notifyStoredRecording($room, $participant, $file);
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
			$this->logger->warning("Uploaded file might be larger than allowed by PHP settings 'upload_max_filesize' or 'post_max_size'");
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
			$this->logger->warning("Uploaded file detected mime type ($mimeType) is not allowed");
			throw new InvalidArgumentException('file_mimetype');
		}

		$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		if (!$extension || !in_array($extension, $allowed[$mimeType])) {
			$this->logger->warning("Uploaded file extensions ($extension) is not allowed for the detected mime type ($mimeType)");
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

	public function notifyStoredRecording(Room $room, Participant $participant, File $file): void {
		$attendee = $participant->getAttendee();

		$notification = $this->notificationManager->createNotification();

		$notification
			->setApp('spreed')
			->setDateTime($this->timeFactory->getDateTime())
			->setObject('recording', $room->getToken())
			->setUser($attendee->getActorId())
			->setSubject('record_file_stored', [
				'objectId' => $file->getId(),
			]);
		$this->notificationManager->notify($notification);
	}

	public function notificationDismiss(Room $room, Participant $participant, int $timestamp): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp('spreed')
			->setObject('recording', $room->getToken())
			->setSubject('record_file_stored')
			->setDateTime($this->timeFactory->getDateTime('@' . $timestamp))
			->setUser($participant->getAttendee()->getActorId());
		$this->notificationManager->markProcessed($notification);
	}

	private function getTypeOfShare(string $mimetype): string {
		if (str_starts_with($mimetype, 'video/')) {
			return 'record-video';
		}
		return 'record-audio';
	}

	public function shareToChat(Room $room, Participant $participant, int $fileId, int $timestamp): void {
		try {
			$userFolder = $this->rootFolder->getUserFolder(
				$participant->getAttendee()->getActorId()
			);
			/** @var \OCP\Files\File[] */
			$files = $userFolder->getById($fileId);
			$file = array_shift($files);
		} catch (\Throwable $th) {
			throw new InvalidArgumentException('file');
		}

		$creationDateTime = $this->timeFactory->getDateTime();

		$share = $this->shareManager->newShare();
		$share->setNodeId($fileId)
			->setShareTime($creationDateTime)
			->setSharedBy($participant->getAttendee()->getActorId())
			->setNode($file)
			->setShareType(IShare::TYPE_ROOM)
			->setSharedWith($room->getToken())
			->setPermissions(\OCP\Constants::PERMISSION_READ);

		$share = $this->shareManager->createShare($share);

		$message = json_encode([
			'message' => 'file_shared',
			'parameters' => [
				'share' => $share->getId(),
				'metaData' => [
					'mimeType' => $file->getMimeType(),
					'messageType' => $this->getTypeOfShare($file->getMimeType()),
				],
			],
		]);

		try {
			$this->chatManager->addSystemMessage(
				$room,
				$participant->getAttendee()->getActorType(),
				$participant->getAttendee()->getActorId(),
				$message,
				$creationDateTime,
				true
			);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InvalidArgumentException('system');
		}
		$this->notificationDismiss($room, $participant, $timestamp);
	}
}

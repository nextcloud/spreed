<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use InvalidArgumentException;
use OC\User\NoUserException;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RecordingNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Recording\BackendNotifier;
use OCA\Talk\Room;
use OCA\Talk\Settings\UserPreference;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\IManager;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use OCP\TaskProcessing\Exception\Exception;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;
use Psr\Log\LoggerInterface;

class RecordingService {
	public const CONSENT_REQUIRED_NO = 0;
	public const CONSENT_REQUIRED_YES = 1;
	public const CONSENT_REQUIRED_OPTIONAL = 2;

	public const APPCONFIG_PREFIX = 'recording/';

	public const DEFAULT_ALLOWED_RECORDING_FORMATS = [
		'audio/ogg' => ['ogg'],
		'video/ogg' => ['ogv'],
		'video/webm' => ['webm'],
		'video/x-matroska' => ['mkv'],
	];
	public const UPLOAD_ERRORS = [
		UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
		UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
		UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded',
		UPLOAD_ERR_NO_FILE => 'No file was uploaded',
		UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
		UPLOAD_ERR_CANT_WRITE => 'Could not write file to disk',
		UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
	];

	public function __construct(
		protected IMimeTypeDetector $mimeTypeDetector,
		protected ParticipantService $participantService,
		protected IRootFolder $rootFolder,
		protected IManager $notificationManager,
		protected Manager $roomManager,
		protected ITimeFactory $timeFactory,
		protected Config $config,
		protected IConfig $serverConfig,
		protected IAppConfig $appConfig,
		protected RoomService $roomService,
		protected ShareManager $shareManager,
		protected ChatManager $chatManager,
		protected LoggerInterface $logger,
		protected BackendNotifier $backendNotifier,
		protected ITaskProcessingManager $taskProcessingManager,
		protected IFactory $l10nFactory,
		protected IUserManager $userManager,
	) {
	}

	/**
	 * @psalm-param Room::RECORDING_* $status
	 */
	public function start(Room $room, int $status, string $owner, Participant $participant): void {
		$availableRecordingTypes = [Room::RECORDING_VIDEO, Room::RECORDING_AUDIO];
		if (!in_array($status, $availableRecordingTypes, true)) {
			throw new InvalidArgumentException('status');
		}
		if ($room->getCallRecording() !== Room::RECORDING_NONE && $room->getCallRecording() !== Room::RECORDING_FAILED) {
			throw new InvalidArgumentException('recording');
		}
		if (!$room->getActiveSince() instanceof \DateTimeInterface) {
			throw new InvalidArgumentException('call');
		}
		if (!$this->config->isRecordingEnabled()) {
			throw new InvalidArgumentException('config');
		}

		$this->backendNotifier->start($room, $status, $owner, $participant);

		$startingStatus = $status === Room::RECORDING_VIDEO ? Room::RECORDING_VIDEO_STARTING : Room::RECORDING_AUDIO_STARTING;
		$this->roomService->setCallRecording($room, $startingStatus);
		$this->appConfig->setAppValueString(self::APPCONFIG_PREFIX . $room->getToken(), $owner, true, true);
	}

	public function stop(Room $room, ?Participant $participant = null): void {
		if ($room->getCallRecording() === Room::RECORDING_NONE) {
			return;
		}

		try {
			$this->backendNotifier->stop($room, $participant);
		} catch (RecordingNotFoundException $e) {
			// If the recording to be stopped is not known to the recording
			// server it will never notify that the recording was stopped, so
			// the status needs to be explicitly changed here.
			$this->roomService->setCallRecording($room, Room::RECORDING_FAILED);
		}
	}

	public function store(Room $room, string $owner, array $file): void {
		$this->appConfig->deleteAppValue(self::APPCONFIG_PREFIX . $room->getToken());
		try {
			$participant = $this->participantService->getParticipant($room, $owner);
		} catch (ParticipantNotFoundException $e) {
			throw new InvalidArgumentException('owner_participant');
		}

		$resource = $this->getResourceFromFileArray($file, $room, $participant);

		$fileName = basename($file['name']);
		$fileRealPath = realpath($file['tmp_name']);

		$this->validateFileFormat($fileName, $fileRealPath);

		try {
			$recordingFolder = $this->getRecordingFolder($owner, $room->getToken());
			$fileNode = $recordingFolder->newFile($fileName, $resource);
			$this->notifyStoredRecording($room, $participant, $fileNode);
		} catch (NoUserException $e) {
			throw new InvalidArgumentException('owner_invalid');
		} catch (NotPermittedException $e) {
			throw new InvalidArgumentException('owner_permission');
		}

		$shouldTranscribe = $this->serverConfig->getAppValue('spreed', 'call_recording_transcription', 'no') === 'yes';
		$shouldSummarize = $this->serverConfig->getAppValue('spreed', 'call_recording_summary', 'yes') === 'yes';
		if (!$shouldTranscribe && !$shouldSummarize) {
			$this->logger->debug('Skipping transcription and summary of call recording, as both are disabled');
			return;
		}

		$supportedTaskTypeIds = $this->taskProcessingManager->getAvailableTaskTypeIds();
		if (!in_array(AudioToText::ID, $supportedTaskTypeIds, true)) {
			$this->logger->error('Can not transcribe call recording as no Audio2Text task provider is available');
			return;
		}

		$task = new Task(
			AudioToText::ID,
			['input' => $fileNode->getId()],
			Application::APP_ID,
			$owner,
			'call/transcription/' . $room->getToken(),
		);

		try {
			$this->taskProcessingManager->scheduleTask($task);
			$this->logger->debug('Scheduled call recording transcript');
		} catch (Exception $e) {
			$this->logger->error('An error occurred while trying to transcribe the call recording', ['exception' => $e]);
		}
	}

	/**
	 * @param 'transcript'|'summary' $aiTask
	 */
	public function storeTranscript(string $owner, string $roomToken, int $recordingFileId, string $output, string $aiTask): void {
		$userFolder = $this->rootFolder->getUserFolder($owner);
		$recordingNodes = $userFolder->getById($recordingFileId);

		if (empty($recordingNodes)) {
			$this->logger->warning("Could not save recording $aiTask as the recording could not be found", [
				'owner' => $owner,
				'roomToken' => $roomToken,
				'recordingFileId' => $recordingFileId,
			]);
			throw new InvalidArgumentException('owner_participant');
		}
		$recording = array_pop($recordingNodes);
		$recordingFolder = $recording->getParent();

		if ($recordingFolder->getName() !== $roomToken) {
			$this->logger->warning("Could not determinate conversation when trying to store $aiTask of call recording, as folder name did not match customId conversation token");
			throw new InvalidArgumentException('owner_participant');
		}

		try {
			$room = $this->roomManager->getRoomForUserByToken($roomToken, $owner);
			$participant = $this->participantService->getParticipant($room, $owner);
		} catch (ParticipantNotFoundException) {
			$this->logger->warning("Could not determinate conversation when trying to store $aiTask of call recording");
			throw new InvalidArgumentException('owner_participant');
		}

		$shouldTranscribe = $this->serverConfig->getAppValue('spreed', 'call_recording_transcription', 'no') === 'yes';
		$shouldSummarize = $this->serverConfig->getAppValue('spreed', 'call_recording_summary', 'yes') === 'yes';

		if ($aiTask === 'transcript') {
			$transcriptFileName = pathinfo($recording->getName(), PATHINFO_FILENAME) . '.md';
			if (!$shouldTranscribe) {
				$this->logger->debug('Skipping saving of transcript for call recording as it is disabled');
			}
		} else {
			$transcriptFileName = pathinfo($recording->getName(), PATHINFO_FILENAME) . ' - ' . $aiTask . '.md';
		}

		if (($shouldTranscribe && $aiTask === 'transcript')
			|| ($shouldSummarize && $aiTask === 'summary')) {
			$user = $this->userManager->get($owner);
			$language = $this->l10nFactory->getUserLanguage($user);
			$l = $this->l10nFactory->get(Application::APP_ID, $language);

			if ($aiTask === 'transcript') {
				$warning = $l->t('Transcript is AI generated and may contain mistakes');
			} else {
				$warning = $l->t('Summary is AI generated and may contain mistakes');
			}

			try {
				$fileNode = $recordingFolder->newFile(
					$transcriptFileName,
					$output . "\n\n$warning\n",
				);
				$this->notifyStoredTranscript($room, $participant, $fileNode, $aiTask);
			} catch (NoUserException) {
				throw new InvalidArgumentException('owner_invalid');
			} catch (NotPermittedException) {
				throw new InvalidArgumentException('owner_permission');
			}
		}

		if (!$shouldSummarize) {
			// If summary is off skip scheduling it
			$this->logger->debug('Skipping scheduling summary of call recording as it is disabled');
			return;
		}

		if ($aiTask === 'summary') {
			// After saving the summary there is nothing more to do
			return;
		}

		$supportedTaskTypeIds = $this->taskProcessingManager->getAvailableTaskTypeIds();
		if (!in_array(TextToTextSummary::ID, $supportedTaskTypeIds, true)) {
			$this->logger->error('Can not summarize call recording as no TextToTextSummary task provider is available');
			return;
		}

		$task = new Task(
			TextToTextSummary::ID,
			['input' => $output],
			Application::APP_ID,
			$owner,
			'call/summary/' . $room->getToken() . '/' . $recordingFileId,
		);

		try {
			$this->taskProcessingManager->scheduleTask($task);
			$this->logger->debug('Scheduled call recording summary');
		} catch (Exception $e) {
			$this->logger->error('An error occurred while trying to summarize the call recording', ['exception' => $e]);
		}
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function notifyAboutFailedStore(Room $room): void {
		$owner = $this->appConfig->getAppValueString(self::APPCONFIG_PREFIX . $room->getToken(), lazy: true);
		if ($owner === '') {
			return;
		}

		try {
			$participant = $this->participantService->getParticipant($room, $owner);
		} catch (ParticipantNotFoundException) {
			$this->logger->warning('Could not determinate conversation when trying to notify about failed upload of call recording');
			throw new InvalidArgumentException('owner_participant');
		}

		$attendee = $participant->getAttendee();

		$notification = $this->notificationManager->createNotification();

		$notification
			->setApp('spreed')
			->setDateTime($this->timeFactory->getDateTime())
			->setObject('recording_information', $room->getToken())
			->setUser($attendee->getActorId())
			->setSubject('record_file_store_fail');
		$this->notificationManager->notify($notification);
	}

	public function notifyAboutFailedTranscript(string $owner, string $roomToken, int $recordingFileId, string $aiType): void {
		$userFolder = $this->rootFolder->getUserFolder($owner);
		$recordingNodes = $userFolder->getById($recordingFileId);

		if (empty($recordingNodes)) {
			$this->logger->warning("Could not trying to notify about failed $aiType as the recording could not be found", [
				'owner' => $owner,
				'roomToken' => $roomToken,
				'recordingFileId' => $recordingFileId,
			]);
			throw new InvalidArgumentException('owner_participant');
		}
		$recording = array_pop($recordingNodes);
		$recordingFolder = $recording->getParent();

		if ($recordingFolder->getName() !== $roomToken) {
			$this->logger->warning("Could not determinate conversation when trying to notify about failed $aiType, as folder name did not match customId conversation token");
			throw new InvalidArgumentException('owner_participant');
		}

		try {
			$room = $this->roomManager->getRoomForUserByToken($roomToken, $owner);
			$participant = $this->participantService->getParticipant($room, $owner);
		} catch (ParticipantNotFoundException) {
			$this->logger->warning("Could not determinate conversation when trying to notify about failed $aiType of call recording");
			throw new InvalidArgumentException('owner_participant');
		}

		$attendee = $participant->getAttendee();

		$notification = $this->notificationManager->createNotification();

		$notification
			->setApp('spreed')
			->setDateTime($this->timeFactory->getDateTime())
			->setObject('recording', $room->getToken())
			->setUser($attendee->getActorId())
			->setSubject($aiType === 'transcript' ? 'transcript_failed' : 'summary_failed', [
				'objectId' => $recording->getId(),
			]);
		$this->notificationManager->notify($notification);
	}

	/**
	 * Gets a resource that represents the file contents of the file array.
	 *
	 * @param array $file File array from which a resource will be returned
	 * @param Room $room The Talk room that requests the resource
	 * @param Participant $participant The Talk participant that requests the resource
	 * @return resource Resource representing the file contents of the file array
	 */
	public function getResourceFromFileArray(array $file, Room $room, Participant $participant) {
		if ($file['error'] !== 0) {
			$error = self::UPLOAD_ERRORS[$file['error']];
			$this->logger->error($error);

			$notification = $this->notificationManager->createNotification();
			$notification
				->setApp('spreed')
				->setDateTime($this->timeFactory->getDateTime())
				->setObject('recording_information', $room->getToken())
				->setUser($participant->getAttendee()->getActorId())
				->setSubject('record_file_store_fail');
			$this->notificationManager->notify($notification);

			throw new InvalidArgumentException('invalid_file');
		}

		$resource = fopen($file['tmp_name'], 'r');
		if ($resource === false) {
			throw new InvalidArgumentException('fopen_failed');
		}

		$resourceStat = fstat($resource);
		if ($resourceStat === false) {
			throw new InvalidArgumentException('fstat_failed');
		}

		if ($resourceStat['size'] === 0) {
			throw new InvalidArgumentException('empty_file');
		}

		return $resource;
	}

	public function validateFileFormat(string $fileName, string $fileRealPath): void {
		if (!is_file($fileRealPath)) {
			$this->logger->warning("An invalid file path ($fileRealPath) was provided");
			throw new InvalidArgumentException('file_invalid_path');
		}

		$mimeType = $this->mimeTypeDetector->detectContent($fileRealPath);
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

	/**
	 * @throws NotPermittedException
	 * @throws NoUserException
	 */
	private function getRecordingFolder(string $owner, string $token): Folder {
		$userFolder = $this->rootFolder->getUserFolder($owner);
		$recordingRootFolderName = $this->config->getRecordingFolder($owner);
		try {
			/** @var Folder */
			$recordingRootFolder = $userFolder->get($recordingRootFolderName);
			if ($recordingRootFolder->isShared()) {
				$this->logger->error('Talk attachment folder for user {userId} is set to a shared folder. Resetting to their root.', [
					'userId' => $owner,
				]);

				$this->serverConfig->setUserValue($owner, 'spreed', UserPreference::ATTACHMENT_FOLDER, '/');
			}
		} catch (NotFoundException $e) {
			/** @var Folder */
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


	/**
	 * @param 'transcript'|'summary' $aiType
	 */
	public function notifyStoredTranscript(Room $room, Participant $participant, File $file, string $aiType): void {
		$attendee = $participant->getAttendee();

		$notification = $this->notificationManager->createNotification();

		$notification
			->setApp('spreed')
			->setDateTime($this->timeFactory->getDateTime())
			->setObject('recording', $room->getToken())
			->setUser($attendee->getActorId())
			->setSubject($aiType === 'transcript' ? 'transcript_file_stored' : 'summary_file_stored', [
				'objectId' => $file->getId(),
			]);
		$this->notificationManager->notify($notification);
	}

	public function notificationDismiss(Room $room, Participant $participant, int $timestamp, ?string $notificationSubject): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp('spreed')
			->setObject('recording', $room->getToken())
			->setDateTime($this->timeFactory->getDateTime('@' . $timestamp))
			->setUser($participant->getAttendee()->getActorId());

		if ($notificationSubject === null) {
			$subjects = ['record_file_stored', 'transcript_file_stored', 'summary_file_stored'];
		} else {
			$subjects = [$notificationSubject];
		}

		foreach ($subjects as $subject) {
			$notification->setSubject($subject);
			$this->notificationManager->markProcessed($notification);
		}
	}

	private function getTypeOfShare(string $mimetype): string {
		if (str_starts_with($mimetype, 'video/')) {
			return ChatManager::VERB_RECORD_VIDEO;
		}
		return ChatManager::VERB_RECORD_AUDIO;
	}

	public function shareToChat(Room $room, Participant $participant, int $fileId, int $timestamp): void {
		try {
			$userFolder = $this->rootFolder->getUserFolder(
				$participant->getAttendee()->getActorId()
			);
			$files = $userFolder->getById($fileId);
			/** @var \OCP\Files\File $file */
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

		$removeNotification = null;
		if (!str_ends_with($file->getName(), '.md')) {
			$removeNotification = 'record_file_stored';
		} elseif (!str_ends_with($file->getName(), ' - summary.md')) {
			$removeNotification = 'transcript_file_stored';
		} elseif (str_ends_with($file->getName(), ' - summary.md')) {
			$removeNotification = 'summary_file_stored';
		}

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
		], JSON_THROW_ON_ERROR);

		try {
			$this->chatManager->addSystemMessage(
				$room,
				$participant,
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
		$this->notificationDismiss($room, $participant, $timestamp, $removeNotification);
	}
}

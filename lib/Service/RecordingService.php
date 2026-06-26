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
use OCP\Constants;
use OCP\EventDispatcher\IEventDispatcher;
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
use OCP\Security\Events\GenerateSecurePasswordEvent;
use OCP\Security\ISecureRandom;
use OCP\Security\PasswordContext;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use OCP\SystemTag\ISystemTagObjectMapper;
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
	public const APPCONFIG_UPLOAD_PREFIX = 'recupload/';

	public const DEFAULT_ALLOWED_RECORDING_FORMATS = [
		'audio/ogg' => ['ogg'],
		'video/ogg' => ['ogv'],
		'video/mp4' => ['mp4'],
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
		private readonly IMimeTypeDetector $mimeTypeDetector,
		private readonly ParticipantService $participantService,
		private readonly IRootFolder $rootFolder,
		private readonly IManager $notificationManager,
		private readonly Manager $roomManager,
		private readonly ITimeFactory $timeFactory,
		private readonly Config $config,
		private readonly IConfig $serverConfig,
		private readonly IAppConfig $appConfig,
		private readonly RoomService $roomService,
		private readonly ShareManager $shareManager,
		private readonly ChatManager $chatManager,
		private readonly LoggerInterface $logger,
		private readonly BackendNotifier $backendNotifier,
		private readonly ITaskProcessingManager $taskProcessingManager,
		private readonly ISystemTagObjectMapper $systemTagMapper,
		private readonly IFactory $l10nFactory,
		private readonly IUserManager $userManager,
		private readonly IEventDispatcher $eventDispatcher,
		private readonly ISecureRandom $secureRandom,
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
		} catch (RecordingNotFoundException) {
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

		$fileName = basename((string)$file['name']);
		$fileRealPath = realpath($file['tmp_name']);

		$this->validateFileFormat($fileName, $fileRealPath);

		try {
			$recordingFolder = $this->getRecordingFolder($owner, $room->getToken());
			$fileNode = $recordingFolder->newFile($fileName, $resource);
		} catch (NoUserException $e) {
			throw new InvalidArgumentException('owner_invalid');
		} catch (NotPermittedException $e) {
			throw new InvalidArgumentException('owner_permission');
		}

		$this->finalizeRecording($room, $participant, $fileNode, $owner);
	}

	/**
	 * Request a temporary password-protected public link share so a recording
	 * backend can upload a (potentially large) recording through the chunked
	 * public WebDAV API instead of a single multipart request.
	 *
	 * The share targets the per-room recording folder with create-only
	 * permissions (file-drop), so previously stored recordings stay private and
	 * the uploaded file lands directly in its final location.
	 *
	 * @return array{token: string, password: string, fileName: string}
	 * @throws InvalidArgumentException
	 */
	public function requestUpload(Room $room, string $owner, string $fileName): array {
		try {
			$participant = $this->participantService->getParticipant($room, $owner);
		} catch (ParticipantNotFoundException) {
			throw new InvalidArgumentException('owner_participant');
		}

		$fileName = $this->sanitizeUploadFileName($fileName);

		if (!$this->shareManager->shareApiAllowLinks()
			|| !$this->shareManager->shareApiLinkAllowPublicUpload()) {
			throw new InvalidArgumentException('sharing_disabled');
		}

		try {
			$recordingFolder = $this->getRecordingFolder($owner, $room->getToken());
		} catch (NoUserException) {
			throw new InvalidArgumentException('owner_invalid');
		} catch (NotPermittedException) {
			throw new InvalidArgumentException('owner_permission');
		}

		$event = new GenerateSecurePasswordEvent(PasswordContext::SHARING);
		$this->eventDispatcher->dispatchTyped($event);
		$password = $event->getPassword() ?? $this->secureRandom->generate(20);

		$expiration = $this->timeFactory->getDateTime();
		$expiration->add(new \DateInterval('P1D'));
		$expiration->setTime(0, 0);

		try {
			$share = $this->shareManager->newShare();
			$share->setNode($recordingFolder);
			$share->setShareType(IShare::TYPE_LINK);
			$share->setPermissions(Constants::PERMISSION_CREATE);
			$share->setSharedBy($owner);
			$share->setShareOwner($owner);
			$share->setLabel('Talk recording upload ' . $room->getToken());
			$share->setExpirationDate($expiration);
			$share->setPassword($password);
			$share = $this->shareManager->createShare($share);
		} catch (\Exception $e) {
			$this->logger->error('Could not create upload share for call recording', ['exception' => $e]);
			throw new InvalidArgumentException('sharing_disabled');
		}

		$this->appConfig->setAppValueString($this->getUploadShareConfigKey($room, $fileName), $share->getToken(), true, true);

		// The recording session is over once the backend requests the upload
		// share; only the (potentially long-running) chunked upload remains. Clear
		// the active-recording marker now so a new recording can be started in this
		// conversation while the previous one is still being uploaded. The marker is
		// only needed to recover the owner for a body-less failed multipart store,
		// which cannot happen on the chunked path (the owner is always provided).
		$this->appConfig->deleteAppValue(self::APPCONFIG_PREFIX . $room->getToken());

		return [
			'token' => $share->getToken(),
			'password' => $password,
			'fileName' => $fileName,
		];
	}

	/**
	 * Finish a chunked upload started with {@see self::requestUpload()}: locate
	 * the uploaded file in the recording folder, validate it and run the same
	 * post-processing as the direct multipart upload, then clean up the
	 * temporary share.
	 *
	 * @throws InvalidArgumentException
	 */
	public function finishUpload(Room $room, string $owner, string $fileName): void {
		try {
			$participant = $this->participantService->getParticipant($room, $owner);
		} catch (ParticipantNotFoundException) {
			throw new InvalidArgumentException('owner_participant');
		}

		$fileName = basename($fileName);

		try {
			$recordingFolder = $this->getRecordingFolder($owner, $room->getToken());
		} catch (NoUserException) {
			throw new InvalidArgumentException('owner_invalid');
		} catch (NotPermittedException) {
			throw new InvalidArgumentException('owner_permission');
		}

		try {
			$fileNode = $recordingFolder->get($fileName);
		} catch (NotFoundException) {
			$this->cleanupUploadShare($room, $fileName);
			throw new InvalidArgumentException('invalid_file');
		}

		if (!$fileNode instanceof File) {
			$this->cleanupUploadShare($room, $fileName);
			throw new InvalidArgumentException('invalid_file');
		}

		if ($fileNode->getSize() === 0) {
			$fileNode->delete();
			$this->cleanupUploadShare($room, $fileName);
			throw new InvalidArgumentException('empty_file');
		}

		try {
			// Trust the file node information so the (potentially large) file does
			// not have to be downloaded again from the storage for content detection.
			$this->validateMimeTypeAndExtension($fileNode->getName(), $fileNode->getMimeType());
		} catch (InvalidArgumentException $e) {
			$fileNode->delete();
			$this->cleanupUploadShare($room, $fileName);
			throw $e;
		}

		$this->finalizeRecording($room, $participant, $fileNode, $owner);

		$this->cleanupUploadShare($room, $fileName);
	}

	/**
	 * Delete the temporary upload share for the given file
	 */
	private function cleanupUploadShare(Room $room, string $fileName): void {
		$configKey = $this->getUploadShareConfigKey($room, $fileName);
		$shareToken = $this->appConfig->getAppValueString($configKey, lazy: true);
		if ($shareToken !== '') {
			try {
				$share = $this->shareManager->getShareByToken($shareToken);
				$this->shareManager->deleteShare($share);
			} catch (ShareNotFound) {
				// Already gone, nothing to clean up
			}
		}
		$this->appConfig->deleteAppValue($configKey);
	}

	private function getUploadShareConfigKey(Room $room, string $fileName): string {
		return self::APPCONFIG_UPLOAD_PREFIX . $room->getToken() . '/' . sha1(basename($fileName));
	}

	/**
	 * Make sure the requested upload file name is a plain name with an allowed
	 * recording extension, so the file dropped into the share already has a
	 * valid extension once the content is validated on finish.
	 *
	 * @throws InvalidArgumentException
	 */
	private function sanitizeUploadFileName(string $fileName): string {
		$fileName = basename($fileName);
		if ($fileName === '') {
			throw new InvalidArgumentException('file_name');
		}

		$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		$allowedExtensions = array_merge(...array_values(self::DEFAULT_ALLOWED_RECORDING_FORMATS));
		if (!$extension || !in_array($extension, $allowedExtensions, true)) {
			throw new InvalidArgumentException('file_extension');
		}

		return $fileName;
	}

	/**
	 * Run the post-processing shared by the direct multipart upload and the
	 * chunked upload: notify the owner and schedule transcription/summary.
	 */
	private function finalizeRecording(Room $room, Participant $participant, File $fileNode, string $owner): void {
		$this->notifyStoredRecording($room, $participant, $fileNode);

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
				$this->systemTagMapper->assignGeneratedByAITag((string)$fileNode->getId(), 'files');
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
		$this->validateMimeTypeAndExtension($fileName, $mimeType);
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private function validateMimeTypeAndExtension(string $fileName, string $mimeType): void {
		$allowed = self::DEFAULT_ALLOWED_RECORDING_FORMATS;
		if (!array_key_exists($mimeType, $allowed)) {
			$this->logger->warning("Uploaded file detected mime type ($mimeType) is not allowed");
			throw new InvalidArgumentException('file_mimetype');
		}

		$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		if (!$extension || !in_array($extension, $allowed[$mimeType], true)) {
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
		} catch (NotFoundException) {
			/** @var Folder */
			$recordingRootFolder = $userFolder->newFolder($recordingRootFolderName);
		}
		try {
			$recordingFolder = $recordingRootFolder->get($token);
		} catch (NotFoundException) {
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
		} catch (\Throwable) {
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

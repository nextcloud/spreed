<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Config;
use OCA\Talk\Exceptions\RoomProperty\UploadsWithoutStorageException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Constants;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotEnoughSpaceException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Security\Events\GenerateSecurePasswordEvent;
use OCP\Security\ISecureRandom;
use OCP\Security\PasswordContext;
use OCP\Security\RateLimiting\ILimiter;
use OCP\Security\RateLimiting\IRateLimitExceededException;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;

/**
 * Guests, email guests and users with a quota of 0 upload into the Guests
 * folder of the moderator who allowed it. Files wait in the Draft folder next
 * to it, uploaded through a temporary create-only link like call recordings.
 */
class UploadsWithoutStorageService {
	public const RATE_LIMIT_UPLOADS_LIMIT = 30;
	public const RATE_LIMIT_UPLOADS_PERIOD = 60 * 60;

	public function __construct(
		private readonly Config $talkConfig,
		private readonly ConversationFolderService $conversationFolderService,
		private readonly RoomService $roomService,
		private readonly IRootFolder $rootFolder,
		private readonly IShareManager $shareManager,
		private readonly IEventDispatcher $eventDispatcher,
		private readonly ISecureRandom $secureRandom,
		private readonly ITimeFactory $timeFactory,
		private readonly ILimiter $limiter,
		private readonly IRequest $request,
		private readonly IUserManager $userManager,
	) {
	}

	public function isAvailable(): bool {
		return $this->talkConfig->isConversationSubfoldersEnabled()
			&& $this->shareManager->shareApiAllowLinks()
			&& $this->shareManager->shareApiLinkAllowPublicUpload();
	}

	public function hasOwnStorage(Participant $participant): bool {
		$attendee = $participant->getAttendee();
		if ($attendee->getActorType() !== Attendee::ACTOR_USERS) {
			return false;
		}

		$user = $this->userManager->get($attendee->getActorId());
		return $user !== null && (int)$user->getQuotaBytes() !== 0;
	}

	/**
	 * @throws UploadsWithoutStorageException
	 */
	public function allowUploads(Participant $moderator, Room $room): void {
		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_TYPE);
		}
		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_BREAKOUT_ROOM);
		}
		if ($room->getObjectType() === Room::OBJECT_TYPE_MATRIX) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_MATRIX);
		}
		if ($room->isFederatedConversation()) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_FEDERATED);
		}
		if (!$this->isAvailable()) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_UNAVAILABLE);
		}
		if (!$this->hasOwnStorage($moderator)) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_STORAGE);
		}

		try {
			$guestsFolder = $this->conversationFolderService->getOrCreateGuestsFolder($moderator->getAttendee()->getActorId(), $room);
		} catch (NotEnoughSpaceException) {
			throw new UploadsWithoutStorageException(UploadsWithoutStorageException::REASON_STORAGE);
		}

		$this->roomService->setGuestsFolderId($room, $guestsFolder->getId());
	}

	/**
	 * @throws NotFoundException
	 */
	public function getGuestsFolder(Room $room): Folder {
		return $this->getGuestsFolderById($room->getGuestsFolderId());
	}

	/**
	 * The Guests folder as seen by its owner
	 *
	 * @throws NotFoundException
	 */
	public function getGuestsFolderById(int $folderId): Folder {
		if ($folderId === 0) {
			throw new NotFoundException('Uploads without storage are off');
		}

		$ownerId = $this->rootFolder->getFirstNodeById($folderId)?->getOwner()?->getUID();
		if ($ownerId === null) {
			throw new NotFoundException('Guests folder ' . $folderId . ' not found');
		}

		$folder = $this->rootFolder->getUserFolder($ownerId)->getFirstNodeById($folderId);
		if (!$folder instanceof Folder) {
			throw new NotFoundException('Guests folder ' . $folderId . ' not found');
		}
		return $folder;
	}

	/**
	 * @throws NotFoundException
	 */
	public function getOwnerId(Node $node): string {
		$owner = $node->getOwner();
		if ($owner === null) {
			throw new NotFoundException('Node has no owner');
		}
		return $owner->getUID();
	}

	/**
	 * @return array{owner: string, path: string}
	 * @throws NotFoundException
	 */
	public function getGuestsFolderLocation(Room $room): array {
		$guestsFolder = $this->getGuestsFolder($room);
		$owner = $guestsFolder->getOwner();
		if ($owner === null) {
			throw new NotFoundException('Guests folder has no owner');
		}

		return [
			'owner' => $owner->getDisplayName(),
			'path' => $this->conversationFolderService->getRelativePath($owner->getUID(), $guestsFolder),
		];
	}

	/**
	 * The Guests folder and the Draft folder next to it, for a participant without storage
	 *
	 * @return array{guestsFolder: Folder, draftFolder: Folder}
	 * @throws NotPermittedException
	 */
	public function getUploadFolders(Participant $participant, Room $room): array {
		if (!in_array($participant->getAttendee()->getActorType(), [Attendee::ACTOR_GUESTS, Attendee::ACTOR_EMAILS, Attendee::ACTOR_USERS], true)
			|| !$this->isAvailable()) {
			throw new NotPermittedException('Uploads without storage are not allowed');
		}

		try {
			$guestsFolder = $this->getGuestsFolder($room);
			$this->conversationFolderService->shareGuestsFolder($guestsFolder, $this->getOwnerId($guestsFolder), $room);
		} catch (NotFoundException|GenericShareException) {
			throw new NotPermittedException('Uploads without storage are not allowed');
		}

		return [
			'guestsFolder' => $guestsFolder,
			'draftFolder' => $this->conversationFolderService->getOrCreateDraftFolder($guestsFolder),
		];
	}

	/**
	 * Create a temporary password-protected create-only link on the Draft folder.
	 * Files uploaded through it must be named "<token>-…".
	 *
	 * @return array{uploadToken: string, uploadPassword: string}
	 * @throws IRateLimitExceededException
	 * @throws NotFoundException
	 */
	public function createUploadLink(Participant $uploader, Room $room, Folder $draftFolder): array {
		$this->registerUpload($uploader);

		$event = new GenerateSecurePasswordEvent(PasswordContext::SHARING);
		$this->eventDispatcher->dispatchTyped($event);
		$password = $event->getPassword() ?? $this->secureRandom->generate(20);

		$expiration = $this->timeFactory->getDateTime();
		$expiration->add(new \DateInterval('P1D'));

		$ownerId = $this->getOwnerId($draftFolder);
		$share = $this->shareManager->newShare();
		$share->setNode($draftFolder)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(Constants::PERMISSION_CREATE)
			->setSharedBy($ownerId)
			->setShareOwner($ownerId)
			->setLabel($this->getUploadLinkLabel($room, $uploader->getAttendee()->getId()))
			->setExpirationDate($expiration)
			->setPassword($password);
		$share = $this->shareManager->createShare($share);

		return [
			'uploadToken' => $share->getToken(),
			'uploadPassword' => $password,
		];
	}

	/**
	 * The file the participant uploaded through their upload link
	 *
	 * @throws NotFoundException
	 */
	public function getUploadedFile(Participant $uploader, Room $room, Folder $draftFolder, string $uploadToken, string $filePath): Node {
		$fileName = basename($filePath);
		if (!str_starts_with($fileName, $this->getUploadLink($uploader, $room, $draftFolder, $uploadToken)->getToken() . '-')) {
			throw new NotFoundException('File was not uploaded through this link');
		}

		try {
			return $draftFolder->get($fileName);
		} catch (NotPermittedException) {
			throw new NotFoundException('File was not uploaded through this link');
		}
	}

	/**
	 * Delete the upload link once none of its files wait in the Draft folder anymore
	 */
	public function finishUpload(Participant $uploader, Room $room, Folder $draftFolder, string $uploadToken): void {
		try {
			$uploadLink = $this->getUploadLink($uploader, $room, $draftFolder, $uploadToken);
		} catch (NotFoundException) {
			return;
		}

		foreach ($draftFolder->getDirectoryListing() as $node) {
			if (str_starts_with($node->getName(), $uploadToken . '-')) {
				return;
			}
		}

		$this->shareManager->deleteShare($uploadLink);
	}

	/**
	 * Delete the upload links of the conversation, or only those of one attendee
	 */
	public function revokeUploadLinks(Folder $guestsFolder, Room $room, ?Attendee $attendee = null): void {
		try {
			$ownerId = $this->getOwnerId($guestsFolder);
		} catch (NotFoundException) {
			return;
		}

		$draftFolder = $this->conversationFolderService->getOrCreateDraftFolder($guestsFolder);
		$label = $this->getUploadLinkLabel($room, $attendee?->getId());
		foreach ($this->shareManager->getSharesBy($ownerId, IShare::TYPE_LINK, $draftFolder, false, -1) as $share) {
			if ($attendee === null ? str_starts_with($share->getLabel(), $label . ' ') : $share->getLabel() === $label) {
				$this->shareManager->deleteShare($share);
			}
		}
	}

	/**
	 * @throws NotFoundException
	 */
	private function getUploadLink(Participant $uploader, Room $room, Folder $draftFolder, string $uploadToken): IShare {
		try {
			$share = $this->shareManager->getShareByToken($uploadToken);
		} catch (ShareNotFound) {
			throw new NotFoundException('Upload link not found');
		}

		if ($share->getShareType() !== IShare::TYPE_LINK
			|| $share->getNodeId() !== $draftFolder->getId()
			|| $share->getLabel() !== $this->getUploadLinkLabel($room, $uploader->getAttendee()->getId())) {
			throw new NotFoundException('Upload link not found');
		}

		return $share;
	}

	private function getUploadLinkLabel(Room $room, ?int $attendeeId): string {
		return 'Talk upload ' . $room->getToken() . ($attendeeId === null ? '' : ' ' . $attendeeId);
	}

	/**
	 * @throws IRateLimitExceededException
	 */
	private function registerUpload(Participant $uploader): void {
		$attendee = $uploader->getAttendee();
		$user = $attendee->getActorType() === Attendee::ACTOR_USERS ? $this->userManager->get($attendee->getActorId()) : null;

		if ($user !== null) {
			$this->limiter->registerUserRequest('talk-uploads-without-storage', self::RATE_LIMIT_UPLOADS_LIMIT, self::RATE_LIMIT_UPLOADS_PERIOD, $user);
		} else {
			$this->limiter->registerAnonRequest('talk-uploads-without-storage', self::RATE_LIMIT_UPLOADS_LIMIT, self::RATE_LIMIT_UPLOADS_PERIOD, $this->request->getRemoteAddress());
		}
	}
}

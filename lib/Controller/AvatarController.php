<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022, Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 * @author Kate Döen <kate.doeen@nextcloud.com>
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

namespace OCA\Talk\Controller;

use InvalidArgumentException;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Middleware\Attribute\FederationSupported;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipantOrLoggedInAndListedConversation;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\RoomFormatter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkRoom from ResponseDefinitions
 */
class AvatarController extends AEnvironmentAwareController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected RoomFormatter $roomFormatter,
		protected AvatarService $avatarService,
		protected IUserSession $userSession,
		protected IL10N $l,
		protected LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 *
	 * Upload an avatar for a room
	 *
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: Avatar uploaded successfully
	 * 400: Avatar invalid
	 */
	#[PublicPage]
	#[RequireModeratorParticipant]
	public function uploadAvatar(): DataResponse {
		try {
			$file = $this->request->getUploadedFile('file');
			$this->avatarService->setAvatarFromRequest($this->getRoom(), $file);
			return new DataResponse($this->roomFormatter->formatRoom(
				$this->getResponseFormat(),
				[],
				$this->getRoom(),
				$this->participant,
			));
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			$this->logger->error('Failed to post avatar', [
				'exception' => $e,
			]);

			return new DataResponse(['message' => $this->l->t('An error occurred. Please contact your administrator.')], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Set an emoji as avatar
	 *
	 * @param string $emoji Emoji
	 * @param ?string $color Color of the emoji
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: Avatar set successfully
	 * 400: Setting emoji avatar is not possible
	 */
	#[PublicPage]
	#[RequireModeratorParticipant]
	public function emojiAvatar(string $emoji, ?string $color): DataResponse {
		try {
			$this->avatarService->setAvatarFromEmoji($this->getRoom(), $emoji, $color);
			return new DataResponse($this->roomFormatter->formatRoom(
				$this->getResponseFormat(),
				[],
				$this->getRoom(),
				$this->participant,
			));
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			$this->logger->error('Failed to post avatar', [
				'exception' => $e,
			]);

			return new DataResponse(['message' => $this->l->t('An error occurred. Please contact your administrator.')], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Get the avatar of a room
	 *
	 * @param bool $darkTheme Theme used for background
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>
	 *
	 * 200: Room avatar returned
	 */
	#[FederationSupported]
	#[PublicPage]
	#[NoCSRFRequired]
	#[RequireParticipantOrLoggedInAndListedConversation]
	public function getAvatar(bool $darkTheme = false): FileDisplayResponse {
		if ($this->room->getRemoteServer()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\AvatarController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\AvatarController::class);
			try {
				return $proxy->getAvatar($this->room, $this->participant, $darkTheme);
			} catch (CannotReachRemoteException) {
				// Falling back to a local "globe" avatar for indicating the federation
			}
		}
		$file = $this->avatarService->getAvatar($this->getRoom(), $this->userSession->getUser(), $darkTheme);

		$response = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => $file->getMimeType()]);
		// Cache for 1 day
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}

	/**
	 * Get the dark mode avatar of a room
	 *
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>
	 *
	 * 200: Room avatar returned
	 */
	#[FederationSupported]
	#[PublicPage]
	#[NoCSRFRequired]
	#[RequireParticipantOrLoggedInAndListedConversation]
	public function getAvatarDark(): FileDisplayResponse {
		return $this->getAvatar(true);
	}

	/**
	 * Delete the avatar of a room
	 *
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{}>
	 *
	 * 200: Avatar removed successfully
	 */
	#[PublicPage]
	#[RequireModeratorParticipant]
	public function deleteAvatar(): DataResponse {
		$this->avatarService->deleteAvatar($this->getRoom());
		return new DataResponse($this->roomFormatter->formatRoom(
			$this->getResponseFormat(),
			[],
			$this->getRoom(),
			$this->participant,
		));
	}
}

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

namespace OCA\Talk\Controller;

use InvalidArgumentException;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\RoomFormatter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

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

	#[PublicPage]
	#[NoCSRFRequired]
	#[RequireParticipant]
	public function getAvatar(bool $darkTheme = false): Response {
		$file = $this->avatarService->getAvatar($this->getRoom(), $this->userSession->getUser(), $darkTheme);

		$response = new FileDisplayResponse($file);
		$response->addHeader('Content-Type', $file->getMimeType());
		// Cache for 1 day
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[RequireParticipant]
	public function getAvatarDark(): Response {
		return $this->getAvatar(true);
	}

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

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation\Proxy\TalkV1\Controller;

use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Federation\Proxy\TalkV1\ProxyRequest;
use OCA\Talk\Model\Invitation;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\SimpleFS\InMemoryFile;

/**
 * @psalm-import-type TalkChatMentionSuggestion from ResponseDefinitions
 * @psalm-import-type TalkChatMessageWithParent from ResponseDefinitions
 */
class AvatarController {
	public function __construct(
		protected ProxyRequest $proxy,
	) {
	}

	/**
	 * @see \OCA\Talk\Controller\AvatarController::getAvatar()
	 *
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Room avatar returned
	 */
	public function getAvatar(Room $room, ?Participant $participant, ?Invitation $invitation, bool $darkTheme): FileDisplayResponse {
		if ($participant === null && $invitation === null) {
			throw new CannotReachRemoteException('Must receive either participant or invitation');
		}

		$proxy = $this->proxy->get(
			$participant ? $participant->getAttendee()->getInvitedCloudId() : $invitation->getLocalCloudId(),
			$participant ? $participant->getAttendee()->getAccessToken() : $invitation->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/room/' . $room->getRemoteToken() . '/avatar' . ($darkTheme ? '/dark' : ''),
		);

		if ($proxy->getStatusCode() !== Http::STATUS_OK) {
			$this->proxy->logUnexpectedStatusCode(__METHOD__, $proxy->getStatusCode(), (string)$proxy->getBody());
			throw new CannotReachRemoteException('Avatar request had unexpected status code');
		}

		$content = $proxy->getBody();
		if ($content === '') {
			throw new CannotReachRemoteException('No avatar content received');
		}

		$file = new InMemoryFile($room->getToken(), $content);

		$response = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => $file->getMimeType()]);
		// Cache for 1 day
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}

	/**
	 * @see \OCA\Talk\Controller\AvatarController::getUserProxyAvatar()
	 *
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: User avatar returned
	 */
	public function getUserProxyAvatar(string $remoteServer, string $user, int $size, bool $darkTheme): FileDisplayResponse {
		$proxy = $this->proxy->get(
			null,
			null,
			$remoteServer . '/index.php/avatar/' . $user . '/' . $size . ($darkTheme ? '/dark' : ''),
		);

		if ($proxy->getStatusCode() !== Http::STATUS_OK) {
			if ($proxy->getStatusCode() !== Http::STATUS_NOT_FOUND) {
				$this->proxy->logUnexpectedStatusCode(__METHOD__, $proxy->getStatusCode(), (string)$proxy->getBody());
			}
			throw new CannotReachRemoteException('Avatar request had unexpected status code');
		}

		$content = $proxy->getBody();
		if ($content === '') {
			throw new CannotReachRemoteException('No avatar content received');
		}

		$file = new InMemoryFile($user, $content);

		$response = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => $file->getMimeType()]);
		// Cache for 1 day
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}
}

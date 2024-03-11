<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Federation\Proxy\TalkV1\Controller;

use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Federation\Proxy\TalkV1\ProxyRequest;
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
	public function getAvatar(Room $room, Participant $participant, bool $darkTheme): FileDisplayResponse {
		$proxy = $this->proxy->get(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/room/' . $room->getRemoteToken() . '/avatar' . ($darkTheme ? '/dark' : ''),
		);

		if ($proxy->getStatusCode() !== Http::STATUS_OK) {
			$this->proxy->logUnexpectedStatusCode(__METHOD__, $proxy->getStatusCode(), (string) $proxy->getBody());
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
				$this->proxy->logUnexpectedStatusCode(__METHOD__, $proxy->getStatusCode(), (string) $proxy->getBody());
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

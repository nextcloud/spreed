<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2021 Gary Kim <gary@garykim.dev>
 *
 * @author Gary Kim <gary@garykim.dev>
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

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setLocalRoomId(int $roomLocalId)
 * @method int getLocalRoomId()
 * @method void setAccessToken(string $accessToken)
 * @method string getAccessToken()
 * @method void setRemoteServerUrl(string $remoteServerUrl)
 * @method string getRemoteServerUrl()
 * @method void setRemoteToken(string $remoteToken)
 * @method string getRemoteToken()
 * @method void setRemoteAttendeeId(int $remoteAttendeeId)
 * @method int getRemoteAttendeeId()
 */
class Invitation extends Entity implements \JsonSerializable {
	protected string $userId = '';
	protected int $localRoomId = 0;
	protected string $accessToken = '';
	protected string $remoteServerUrl = '';
	protected string $remoteToken = '';
	protected int $remoteAttendeeId = 0;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('localRoomId', 'int');
		$this->addType('accessToken', 'string');
		$this->addType('remoteServerUrl', 'string');
		$this->addType('remoteToken', 'string');
		$this->addType('remoteAttendeeId', 'int');
	}

	/**
	 * @return array{access_token: string, id: int, local_room_id: int, remote_attendee_id: int, remote_server_url: string, remote_token: string, user_id: string}
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'user_id' => $this->getUserId(),
			'local_room_id' => $this->getLocalRoomId(),
			'access_token' => $this->getAccessToken(),
			'remote_server_url' => $this->getRemoteServerUrl(),
			'remote_token' => $this->getRemoteToken(),
			'remote_attendee_id' => $this->getRemoteAttendeeId(),
		];
	}
}

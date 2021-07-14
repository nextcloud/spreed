<?php

declare(strict_types=1);
/**
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
 * Class Invitation
 *
 * @package OCA\Talk\Model
 *
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setAccessToken(string $accessToken)
 * @method string getAccessToken()
 * @method void setRemoteId(string $remoteId)
 * @method string getRemoteId()
 */
class Invitation extends Entity {
	/** @var int */
	protected $roomId;

	/** @var string */
	protected $userId;

	/** @var string */
	protected $accessToken;

	/** @var string */
	protected $remoteId;

	public function __construct() {
		$this->addType('roomId', 'int');
		$this->addType('userId', 'string');
		$this->addType('accessToken', 'string');
		$this->addType('remoteId', 'string');
	}

	public function asArray(): array {
		return [
			'id' => $this->getId(),
			'room_id' => $this->getRoomId(),
			'user_id' => $this->getUserId(),
			'access_token' => $this->getAccessToken(),
			'remote_id' => $this->getRemoteId(),
		];
	}
}

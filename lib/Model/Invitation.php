<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setState(int $state)
 * @method int getState()
 * @method void setLocalRoomId(int $localRoomId)
 * @method int getLocalRoomId()
 * @method void setAccessToken(string $accessToken)
 * @method string getAccessToken()
 * @method void setRemoteServerUrl(string $remoteServerUrl)
 * @method string getRemoteServerUrl()
 * @method void setRemoteToken(string $remoteToken)
 * @method string getRemoteToken()
 * @method void setRemoteAttendeeId(int $remoteAttendeeId)
 * @method int getRemoteAttendeeId()
 * @method void setInviterCloudId(string $inviterCloudId)
 * @method string getInviterCloudId()
 * @method void setInviterDisplayName(string $inviterDisplayName)
 * @method string getInviterDisplayName()
 * @method void setLocalCloudId(string $localCloudId)
 * @method string getLocalCloudId()
 */
class Invitation extends Entity implements \JsonSerializable {
	public const STATE_PENDING = 0;
	public const STATE_ACCEPTED = 1;

	protected string $userId = '';
	protected int $state = self::STATE_PENDING;
	protected int $localRoomId = 0;
	protected string $accessToken = '';
	protected string $remoteServerUrl = '';
	protected string $remoteToken = '';
	protected int $remoteAttendeeId = 0;
	protected string $inviterCloudId = '';
	protected string $inviterDisplayName = '';
	protected string $localCloudId = '';

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('state', Types::SMALLINT);
		$this->addType('localRoomId', Types::BIGINT);
		$this->addType('accessToken', Types::STRING);
		$this->addType('remoteServerUrl', Types::STRING);
		$this->addType('remoteToken', Types::STRING);
		$this->addType('remoteAttendeeId', Types::BIGINT);
		$this->addType('inviterCloudId', Types::STRING);
		$this->addType('inviterDisplayName', Types::STRING);
		$this->addType('localCloudId', Types::STRING);
	}

	/**
	 * @return array{id: int, localCloudId: string, remoteAttendeeId: int, remoteServerUrl: string, remoteToken: string, state: int, userId: string, inviterCloudId: string, inviterDisplayName: string}
	 */
	#[\Override]
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'state' => $this->getState(),
			'localCloudId' => $this->getLocalCloudId(),
			'remoteServerUrl' => $this->getRemoteServerUrl(),
			'remoteToken' => $this->getRemoteToken(),
			'remoteAttendeeId' => $this->getRemoteAttendeeId(),
			'inviterCloudId' => $this->getInviterCloudId(),
			'inviterDisplayName' => $this->getInviterDisplayName() ?: $this->getInviterCloudId(),
		];
	}
}

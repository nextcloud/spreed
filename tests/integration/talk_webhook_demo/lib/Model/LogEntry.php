<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TalkWebhookDemo\Model;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setServer(string $server)
 * @method string getServer()
 * @method void setToken(string $token)
 * @method string getToken()
 * @method void setType(string $type)
 * @method string getType()
 * @method void setDetails(?string $details)
 * @method string|null getDetails()
 */
class LogEntry extends Entity {
	public const TYPE_ATTENDEE = 'attendee';
	public const TYPE_START = 'start';
	public const TYPE_ELEVATOR = 'elevator';
	public const TYPE_TODO = 'todo';
	public const TYPE_SOLVED = 'solved';
	public const TYPE_NOTE = 'note';
	public const TYPE_REPORT = 'report';
	public const TYPE_DECISION = 'decision';
	public const TYPE_AGENDA = 'agenda';

	/** @var string */
	protected $server;

	/** @var string */
	protected $token;

	/** @var string */
	protected $type;

	/** @var ?string */
	protected $details;

	public function __construct() {
		$this->addType('server', 'string');
		$this->addType('token', 'string');
		$this->addType('type', 'string');
		$this->addType('details', 'string');
	}
}

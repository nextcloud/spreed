<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Room;
use OCP\EventDispatcher\Event;

abstract class ARoomEvent extends Event {


	public function __construct(
		protected Room $room,
	) {
		parent::__construct();
	}

	public function getRoom(): Room {
		return $this->room;
	}
}

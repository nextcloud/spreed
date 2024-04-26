<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Talk\Events;

use OCP\EventDispatcher\Event;
use OCP\Share\IShare;

class BeforeDuplicateShareSentEvent extends Event {
	public function __construct(
		private IShare $share,
	) {
		parent::__construct();
	}


	public function getShare(): IShare {
		return $this->share;
	}
}

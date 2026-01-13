<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Sharing\Event {

	use OCP\EventDispatcher\Event;
	use OCP\IUser;

	class UserShareAccessUpdatedEvent extends Event {
		public function __construct(IUser $user) {
		}
	}
}

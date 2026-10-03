<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_Sharing {

	use OCP\Files\Mount\IMountPoint;
	use OCP\IUser;
	use OCP\Share\IShare;

	abstract class SharedMount implements IMountPoint {
		public function getShare(): IShare {
		}

		public function getUser(): IUser {
		}
	}
}

namespace OCA\Files_Sharing\Event {

	use OCA\Files_Sharing\SharedMount;
	use OCP\EventDispatcher\Event;
	use OCP\IUser;

	class UserShareAccessUpdatedEvent extends Event {
		public function __construct(IUser $user) {
		}
	}

	class ShareMountedEvent extends Event {
		public function getMount(): SharedMount {
		}
	}
}

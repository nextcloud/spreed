<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\User;

use OCP\IUser;
use OCP\IUserManager;
use OCP\UserInterface;

class LazyUser implements IUser {

	public function __construct(string $uid, IUserManager $userManager, ?string $displayName = null, ?UserInterface $backend = null) {
	}
}

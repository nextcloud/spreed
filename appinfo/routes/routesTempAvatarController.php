<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\TempAvatarController::postAvatar() */
		['name' => 'TempAvatar#postAvatar', 'url' => '/temp-user-avatar', 'verb' => 'POST'],
		/** @see \OCA\Talk\Controller\TempAvatarController::deleteAvatar() */
		['name' => 'TempAvatar#deleteAvatar', 'url' => '/temp-user-avatar', 'verb' => 'DELETE'],
	],
];

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\SettingsController::setSIPSettings() */
		['name' => 'Settings#setSIPSettings', 'url' => '/api/{apiVersion}/settings/sip', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\SettingsController::setUserSetting() */
		['name' => 'Settings#setUserSetting', 'url' => '/api/{apiVersion}/settings/user', 'verb' => 'POST', 'requirements' => $requirements],
	],
];

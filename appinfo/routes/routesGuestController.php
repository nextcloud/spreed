<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\GuestController::setDisplayName() */
		['name' => 'Guest#setDisplayName', 'url' => '/api/{apiVersion}/guest/{token}/name', 'verb' => 'POST', 'requirements' => $requirements],
	],
];

<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\DashboardController::getCalendarRooms() */
		['name' => 'Dashboard#getCalendarRooms', 'url' => '/api/{apiVersion}/dashboard/', 'verb' => 'GET', 'requirements' => $requirements],
	],
];

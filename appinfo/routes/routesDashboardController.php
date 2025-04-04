<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v4)',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\DashboardController::getEventRooms() */
		['name' => 'Dashboard#getEventRooms', 'url' => '/api/{apiVersion}/dashboard/events', 'verb' => 'GET', 'requirements' => $requirements],
	],
];

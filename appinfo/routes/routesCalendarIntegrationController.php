<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v4)',
];

$requirementsWithToken = [
	'apiVersion' => '(v4)',
	'token' => '[a-z0-9]{4,30}',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\CalendarIntegrationController::getDashboardEvents() */
		['name' => 'CalendarIntegration#getDashboardEvents', 'url' => '/api/{apiVersion}/dashboard/events', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CalendarIntegrationController::getMutualEvents() */
		['name' => 'CalendarIntegration#getMutualEvents', 'url' => '/api/{apiVersion}/room/{token}/mutual-events', 'verb' => 'GET', 'requirements' => $requirementsWithToken],
	],
];

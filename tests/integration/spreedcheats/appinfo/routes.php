<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'ocs' => [
		['name' => 'Api#resetSpreed', 'url' => '/', 'verb' => 'DELETE'],
		['name' => 'Api#ageChat', 'url' => '/age', 'verb' => 'POST'],
		['name' => 'Api#createEventInCalendar', 'url' => '/calendar', 'verb' => 'POST'],
		['name' => 'Api#createDashboardEvents', 'url' => '/dashboardEvents', 'verb' => 'POST'],
		['name' => 'Api#createEventAndInviteParticipant', 'url' => '/mutualEvents', 'verb' => 'POST'],
	],
];

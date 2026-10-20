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
		['name' => 'Api#emailAccessToken', 'url' => '/email/access', 'verb' => 'GET'],
		['name' => 'Api#forgedFederationLeave', 'url' => '/forged/federation/active', 'verb' => 'DELETE'],
		['name' => 'Api#deleteUserRoomShares', 'url' => '/forged/userroom-shares', 'verb' => 'DELETE'],
		['name' => 'Api#moveShareMountsToPlaceholder', 'url' => '/forged/share-mounts', 'verb' => 'PUT'],
		['name' => 'Api#listShareMounts', 'url' => '/share-mounts', 'verb' => 'GET'],
		['name' => 'Api#createEventInCalendar', 'url' => '/calendar', 'verb' => 'POST'],
		['name' => 'Api#createDashboardEvents', 'url' => '/dashboardEvents', 'verb' => 'POST'],
		['name' => 'Api#createEventAndInviteParticipant', 'url' => '/mutualEvents', 'verb' => 'POST'],
	],
];

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
		/** @see \OCA\Talk\Controller\PublicShareAuthController::createRoom() */
		['name' => 'PublicShareAuth#createRoom', 'url' => '/api/{apiVersion}/publicshareauth', 'verb' => 'POST', 'requirements' => $requirements],
	],
];

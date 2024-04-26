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
		/** @see \OCA\Talk\Controller\HostedSignalingServerController::requestTrial() */
		['name' => 'HostedSignalingServer#requestTrial', 'url' => '/api/{apiVersion}/hostedsignalingserver/requesttrial', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\HostedSignalingServerController::auth() */
		['name' => 'HostedSignalingServer#auth', 'url' => '/api/{apiVersion}/hostedsignalingserver/auth', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\HostedSignalingServerController::deleteAccount() */
		['name' => 'HostedSignalingServer#deleteAccount', 'url' => '/api/{apiVersion}/hostedsignalingserver/delete', 'verb' => 'DELETE', 'requirements' => $requirements],
	],
];

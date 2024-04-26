<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'token' => '[a-z0-9]{4,30}',
];

return [
	'routes' => [
		/** @see \OCA\Talk\Controller\PageController::index() */
		['name' => 'Page#index', 'url' => '/', 'verb' => 'GET'],
		/** @see \OCA\Talk\Controller\PageController::notFound() */
		['name' => 'Page#notFound', 'url' => '/not-found', 'verb' => 'GET'],
		/** @see \OCA\Talk\Controller\PageController::duplicateSession() */
		['name' => 'Page#duplicateSession', 'url' => '/duplicate-session', 'verb' => 'GET'],
		/** @see \OCA\Talk\Controller\PageController::showCall() */
		['name' => 'Page#showCall', 'url' => '/call/{token}', 'root' => '', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\PageController::authenticatePassword() */
		['name' => 'Page#authenticatePassword', 'url' => '/call/{token}', 'root' => '', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\PageController::recording() */
		['name' => 'Page#recording', 'url' => '/call/{token}/recording', 'root' => '', 'verb' => 'GET', 'requirements' => $requirements],
	],
];

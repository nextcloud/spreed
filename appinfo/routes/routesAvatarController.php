<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
	'token' => '^[a-z0-9]{4,30}$',
];
$requirementsWithSize = [
	'apiVersion' => '(v1)',
	'token' => '^[a-z0-9]{4,30}$',
	'size' => '(64|512)',
];
$requirementsNewWithSize = [
	'apiVersion' => '(v1)',
	'size' => '(64|512)',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\AvatarController::uploadAvatar() */
		['name' => 'Avatar#uploadAvatar', 'url' => '/api/{apiVersion}/room/{token}/avatar', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\AvatarController::emojiAvatar() */
		['name' => 'Avatar#emojiAvatar', 'url' => '/api/{apiVersion}/room/{token}/avatar/emoji', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\AvatarController::getAvatar() */
		['name' => 'Avatar#getAvatar', 'url' => '/api/{apiVersion}/room/{token}/avatar', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\AvatarController::getAvatarDark() */
		['name' => 'Avatar#getAvatarDark', 'url' => '/api/{apiVersion}/room/{token}/avatar/dark', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\AvatarController::deleteAvatar() */
		['name' => 'Avatar#deleteAvatar', 'url' => '/api/{apiVersion}/room/{token}/avatar', 'verb' => 'DELETE', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\AvatarController::getUserProxyAvatarWithoutRoom() */
		['name' => 'Avatar#getUserProxyAvatarWithoutRoom', 'url' => '/api/{apiVersion}/proxy/new/user-avatar/{size}', 'verb' => 'GET', 'requirements' => $requirementsNewWithSize],
		/** @see \OCA\Talk\Controller\AvatarController::getUserProxyAvatarDarkWithoutRoom() */
		['name' => 'Avatar#getUserProxyAvatarDarkWithoutRoom', 'url' => '/api/{apiVersion}/proxy/new/user-avatar/{size}/dark', 'verb' => 'GET', 'requirements' => $requirementsNewWithSize],
		/** @see \OCA\Talk\Controller\AvatarController::getUserProxyAvatar() */
		['name' => 'Avatar#getUserProxyAvatar', 'url' => '/api/{apiVersion}/proxy/{token}/user-avatar/{size}', 'verb' => 'GET', 'requirements' => $requirementsWithSize],
		/** @see \OCA\Talk\Controller\AvatarController::getUserProxyAvatarDark() */
		['name' => 'Avatar#getUserProxyAvatarDark', 'url' => '/api/{apiVersion}/proxy/{token}/user-avatar/{size}/dark', 'verb' => 'GET', 'requirements' => $requirementsWithSize],
	],
];

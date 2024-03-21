<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022, Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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

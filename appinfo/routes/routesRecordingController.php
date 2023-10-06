<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Vitor Mattos <vitor@php.rio>
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
	'token' => '[a-z0-9]{4,30}',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\RecordingController::getWelcomeMessage() */
		['name' => 'Recording#getWelcomeMessage', 'url' => '/api/{apiVersion}/recording/welcome/{serverId}', 'verb' => 'GET', 'requirements' => array_merge($requirements, [
			'serverId' => '\d+',
		])],
		/** @see \OCA\Talk\Controller\RecordingController::backend() */
		['name' => 'Recording#backend', 'url' => '/api/{apiVersion}/recording/backend', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\RecordingController::start() */
		['name' => 'Recording#start', 'url' => '/api/{apiVersion}/recording/{token}', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\RecordingController::stop() */
		['name' => 'Recording#stop', 'url' => '/api/{apiVersion}/recording/{token}', 'verb' => 'DELETE', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\RecordingController::store() */
		['name' => 'Recording#store', 'url' => '/api/{apiVersion}/recording/{token}/store', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\RecordingController::notificationDismiss() */
		['name' => 'Recording#notificationDismiss', 'url' => '/api/{apiVersion}/recording/{token}/notification', 'verb' => 'DELETE', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\RecordingController::shareToChat() */
		['name' => 'Recording#shareToChat', 'url' => '/api/{apiVersion}/recording/{token}/share-chat', 'verb' => 'POST', 'requirements' => $requirements],
	],
];

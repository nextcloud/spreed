<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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
	'apiVersion' => '(v3)',
];


$requirementsWithToken = [
	'apiVersion' => '(v3)',
	'token' => '[a-z0-9]{4,30}',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\SignalingController::getSettings() */
		['name' => 'Signaling#getSettings', 'url' => '/api/{apiVersion}/signaling/settings', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\SignalingController::getWelcomeMessage() */
		['name' => 'Signaling#getWelcomeMessage', 'url' => '/api/{apiVersion}/signaling/welcome/{serverId}', 'verb' => 'GET', 'requirements' => array_merge($requirements, [
			'serverId' => '\d+',
		])],
		/** @see \OCA\Talk\Controller\SignalingController::backend() */
		['name' => 'Signaling#backend', 'url' => '/api/{apiVersion}/signaling/backend', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\SignalingController::signaling() */
		['name' => 'Signaling#signaling', 'url' => '/api/{apiVersion}/signaling/{token}', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\SignalingController::pullMessages() */
		['name' => 'Signaling#pullMessages', 'url' => '/api/{apiVersion}/signaling/{token}', 'verb' => 'GET', 'requirements' => $requirementsWithToken],
	],
];

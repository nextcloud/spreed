<?php
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

return [
	'routes' => [
		[
			'name' => 'page#index',
			'url' => '/',
			'verb' => 'GET',
		],
		[
			'name' => 'signalling#signalling',
			'url' => '/signalling',
			'verb' => 'POST',
		],
		[
			'name' => 'signalling#pullMessages',
			'url' => '/messages',
			'verb' => 'GET',
		],
		[
			'name' => 'AppSettings#setSpreedSettings',
			'url' => '/settings/admin',
			'verb' => 'POST',
		],
	],
	'ocs' => [
		[
			'name' => 'api#getRooms',
			'url' => '/api/{apiVersion}/room',
			'verb' => 'GET',
			'requirements' => ['apiVersion' => 'v1'],
		],
		[
			'name' => 'api#makePublic',
			'url' => '/api/{apiVersion}/room/public',
			'verb' => 'POST',
			'requirements' => ['apiVersion' => 'v1'],
		],
		[
			'name' => 'api#makePrivate',
			'url' => '/api/{apiVersion}/room/public',
			'verb' => 'DELETE',
			'requirements' => ['apiVersion' => 'v1'],
		],
		[
			'name' => 'api#getRoom',
			'url' => '/api/{apiVersion}/room/{token}',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'api#renameRoom',
			'url' => '/api/{apiVersion}/room/{roomId}',
			'verb' => 'PUT',
			'requirements' => [
				'apiVersion' => 'v1',
				'roomId' => '\d+'
			],
		],
		[
			'name' => 'api#addParticipantToRoom',
			'url' => '/api/{apiVersion}/room/{roomId}',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'roomId' => '\d+'
			],
		],
		[
			'name' => 'api#leaveRoom',
			'url' => '/api/{apiVersion}/room/{roomId}',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1',
				'roomId' => '\d+'
			],
		],
		[
			'name' => 'api#getPeersInRoom',
			'url' => '/api/{apiVersion}/room/{token}/peers',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'api#joinRoom',
			'url' => '/api/{apiVersion}/room/{token}/join',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'api#ping',
			'url' => '/api/{apiVersion}/ping',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'api#leave',
			'url' => '/api/{apiVersion}/leave',
			'verb' => 'DELETE',
			'requirements' => ['apiVersion' => 'v1'],
		],
		[
			'name' => 'api#createOneToOneRoom',
			'url' => '/api/{apiVersion}/oneToOne',
			'verb' => 'PUT',
			'requirements' => ['apiVersion' => 'v1'],
		],
		[
			'name' => 'api#createGroupRoom',
			'url' => '/api/{apiVersion}/group',
			'verb' => 'PUT',
			'requirements' => ['apiVersion' => 'v1'],
		],
		[
			'name' => 'api#createPublicRoom',
			'url' => '/api/{apiVersion}/public',
			'verb' => 'PUT',
			'requirements' => ['apiVersion' => 'v1'],
		],
	],
];


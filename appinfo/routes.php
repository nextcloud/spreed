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
			'name' => 'Page#index',
			'url' => '/',
			'verb' => 'GET',
		],
	],
	'ocs' => [
		/**
		 * Signaling
		 */
		[
			'name' => 'Signaling#getSettings',
			'url' => '/api/{apiVersion}/signaling/settings',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1',
			],
		],
		[
			'name' => 'Signaling#backend',
			'url' => '/api/{apiVersion}/signaling/backend',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
			],
		],
		[
			'name' => 'Signaling#signaling',
			'url' => '/api/{apiVersion}/signaling/{token}',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Signaling#pullMessages',
			'url' => '/api/{apiVersion}/signaling/{token}',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],

		/**
		 * Call
		 */
		[
			'name' => 'Call#getPeersForCall',
			'url' => '/api/{apiVersion}/call/{token}',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Call#joinCall',
			'url' => '/api/{apiVersion}/call/{token}',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Call#leaveCall',
			'url' => '/api/{apiVersion}/call/{token}',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],

		/**
		 * Chat
		 */
		[
			'name' => 'Chat#receiveMessages',
			'url' => '/api/{apiVersion}/chat/{token}',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Chat#sendMessage',
			'url' => '/api/{apiVersion}/chat/{token}',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Chat#setReadMarker',
			'url' => '/api/{apiVersion}/chat/{token}/read',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Chat#mentions',
			'url' => '/api/{apiVersion}/chat/{token}/mentions',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],

		/**
		 * Room
		 */
		[
			'name' => 'Room#getRooms',
			'url' => '/api/{apiVersion}/room',
			'verb' => 'GET',
			'requirements' => ['apiVersion' => 'v1'],
		],
		[
			'name' => 'Room#createRoom',
			'url' => '/api/{apiVersion}/room',
			'verb' => 'POST',
			'requirements' => ['apiVersion' => 'v1'],
		],
		[
			'name' => 'Room#getRoom',
			'url' => '/api/{apiVersion}/room/{token}',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#renameRoom',
			'url' => '/api/{apiVersion}/room/{token}',
			'verb' => 'PUT',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#deleteRoom',
			'url' => '/api/{apiVersion}/room/{token}',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#makePublic',
			'url' => '/api/{apiVersion}/room/{token}/public',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#makePrivate',
			'url' => '/api/{apiVersion}/room/{token}/public',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#setPassword',
			'url' => '/api/{apiVersion}/room/{token}/password',
			'verb' => 'PUT',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#getParticipants',
			'url' => '/api/{apiVersion}/room/{token}/participants',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#addParticipantToRoom',
			'url' => '/api/{apiVersion}/room/{token}/participants',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#inviteEmailToRoom',
			'url' => '/api/{apiVersion}/room/{token}/participants/guests',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#removeParticipantFromRoom',
			'url' => '/api/{apiVersion}/room/{token}/participants',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#removeSelfFromRoom',
			'url' => '/api/{apiVersion}/room/{token}/participants/self',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#removeGuestFromRoom',
			'url' => '/api/{apiVersion}/room/{token}/participants/guests',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#joinRoom',
			'url' => '/api/{apiVersion}/room/{token}/participants/active',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#leaveRoom',
			'url' => '/api/{apiVersion}/room/{token}/participants/active',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#promoteModerator',
			'url' => '/api/{apiVersion}/room/{token}/moderators',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#demoteModerator',
			'url' => '/api/{apiVersion}/room/{token}/moderators',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#addToFavorites',
			'url' => '/api/{apiVersion}/room/{token}/favorite',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#removeFromFavorites',
			'url' => '/api/{apiVersion}/room/{token}/favorite',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
		[
			'name' => 'Room#setNotificationLevel',
			'url' => '/api/{apiVersion}/room/{token}/notify',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],


		/**
		 * PublicShareAuth
		 */
		[
			'name' => 'PublicShareAuth#createRoom',
			'url' => '/api/{apiVersion}/publicshareauth',
			'verb' => 'POST',
			'requirements' => ['apiVersion' => 'v1'],
		],

		/**
		 * Guest
		 */
		[
			'name' => 'Guest#setDisplayName',
			'url' => '/api/{apiVersion}/guest/{token}/name',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
				'token' => '^[a-z0-9]{4,30}$',
			],
		],
	],
];


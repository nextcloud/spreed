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
			'name' => 'api#getRooms',
			'url' => '/api/room',
			'verb' => 'GET',
		],
		[
			'name' => 'api#makePublic',
			'url' => '/api/room/public',
			'verb' => 'POST',
		],
		[
			'name' => 'api#makePrivate',
			'url' => '/api/room/public',
			'verb' => 'DELETE',
		],
		[
			'name' => 'api#getRoom',
			'url' => '/api/room/{roomId}',
			'verb' => 'GET',
			'requirements' => ['roomId' => '\d+'],
		],
		[
			'name' => 'api#renameRoom',
			'url' => '/api/room/{roomId}',
			'verb' => 'PUT',
			'requirements' => ['roomId' => '\d+'],
		],
		[
			'name' => 'api#addParticipantToRoom',
			'url' => '/api/room/{roomId}',
			'verb' => 'POST',
			'requirements' => ['roomId' => '\d+'],
		],
		[
			'name' => 'api#leaveRoom',
			'url' => '/api/room/{roomId}',
			'verb' => 'DELETE',
			'requirements' => ['roomId' => '\d+'],
		],
		[
			'name' => 'api#getPeersInRoom',
			'url' => '/api/room/{roomId}/peers',
			'verb' => 'GET',
			'requirements' => ['roomId' => '\d+'],
		],
		[
			'name' => 'api#joinRoom',
			'url' => '/api/room/{roomId}/join',
			'verb' => 'POST',
		],
		[
			'name' => 'api#ping',
			'url' => '/api/ping',
			'verb' => 'POST',
		],
		[
			'name' => 'api#leave',
			'url' => '/api/leave',
			'verb' => 'DELETE',
		],
		[
			'name' => 'AppSettings#setSpreedSettings',
			'url' => '/settings/admin',
			'verb' => 'POST',
		],
		[
			'name' => 'PersonalSettings#setSpreedSettings',
			'url' => '/settings/personal',
			'verb' => 'POST',
		],
		[
			'name' => 'api#createOneToOneRoom',
			'url' => '/api/oneToOne',
			'verb' => 'PUT',
		],
		[
			'name' => 'api#createGroupRoom',
			'url' => '/api/group',
			'verb' => 'PUT',
		],
		[
			'name' => 'api#createPublicRoom',
			'url' => '/api/public',
			'verb' => 'PUT',
		],
	],
];


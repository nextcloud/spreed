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
	'apiVersion' => 'v1',
	'token' => '^[a-z0-9]{4,30}$',
];

$requirementsWithMessageId = [
	'apiVersion' => 'v1',
	'token' => '^[a-z0-9]{4,30}$',
	'messageId' => '^[0-9]+$',
];

return [
	'ocs' => [
		['name' => 'Chat#receiveMessages', 'url' => '/api/{apiVersion}/chat/{token}', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'Chat#sendMessage', 'url' => '/api/{apiVersion}/chat/{token}', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'Chat#clearHistory', 'url' => '/api/{apiVersion}/chat/{token}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'Chat#deleteMessage', 'url' => '/api/{apiVersion}/chat/{token}/{messageId}', 'verb' => 'DELETE', 'requirements' => $requirementsWithMessageId],
		['name' => 'Chat#setReadMarker', 'url' => '/api/{apiVersion}/chat/{token}/read', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'Chat#markUnread', 'url' => '/api/{apiVersion}/chat/{token}/read', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'Chat#mentions', 'url' => '/api/{apiVersion}/chat/{token}/mentions', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'Chat#shareObjectToChat', 'url' => '/api/{apiVersion}/chat/{token}/share', 'verb' => 'POST', 'requirements' => $requirements],
	],
];

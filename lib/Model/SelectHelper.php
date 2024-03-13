<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Model;

use OCP\DB\QueryBuilder\IQueryBuilder;

class SelectHelper {
	public function selectRoomsTable(IQueryBuilder $query, string $alias = 'r'): void {
		if ($alias !== '') {
			$alias .= '.';
		}

		$query->addSelect($alias . 'type')
			->addSelect($alias . 'read_only')
			->addSelect($alias . 'lobby_state')
			->addSelect($alias . 'sip_enabled')
			->addSelect($alias . 'assigned_hpb')
			->addSelect($alias . 'token')
			->addSelect($alias . 'name')
			->addSelect($alias . 'description')
			->addSelect($alias . 'password')
			->addSelect($alias . 'avatar')
			->addSelect($alias . 'active_guests')
			->addSelect($alias . 'active_since')
			->addSelect($alias . 'default_permissions')
			->addSelect($alias . 'call_permissions')
			->addSelect($alias . 'call_flag')
			->addSelect($alias . 'last_activity')
			->addSelect($alias . 'last_message')
			->addSelect($alias . 'lobby_timer')
			->addSelect($alias . 'object_type')
			->addSelect($alias . 'object_id')
			->addSelect($alias . 'listable')
			->addSelect($alias . 'message_expiration')
			->addSelect($alias . 'remote_server')
			->addSelect($alias . 'remote_token')
			->addSelect($alias . 'breakout_room_mode')
			->addSelect($alias . 'breakout_room_status')
			->addSelect($alias . 'call_recording')
			->addSelect($alias . 'recording_consent')
			->selectAlias($alias . 'id', 'r_id');
	}

	public function selectAttendeesTable(IQueryBuilder $query, string $alias = 'a'): void {
		if ($alias !== '') {
			$alias .= '.';
		}

		$query->addSelect($alias . 'room_id')
			->addSelect($alias . 'actor_type')
			->addSelect($alias . 'actor_id')
			->addSelect($alias . 'display_name')
			->addSelect($alias . 'pin')
			->addSelect($alias . 'participant_type')
			->addSelect($alias . 'favorite')
			->addSelect($alias . 'notification_level')
			->addSelect($alias . 'notification_calls')
			->addSelect($alias . 'last_joined_call')
			->addSelect($alias . 'last_read_message')
			->addSelect($alias . 'last_mention_message')
			->addSelect($alias . 'last_mention_direct')
			->addSelect($alias . 'read_privacy')
			->addSelect($alias . 'permissions')
			->addSelect($alias . 'access_token')
			->addSelect($alias . 'remote_id')
			->addSelect($alias . 'invited_cloud_id')
			->addSelect($alias . 'phone_number')
			->addSelect($alias . 'call_id')
			->addSelect($alias . 'state')
			->addSelect($alias . 'unread_messages')
			->selectAlias($alias . 'id', 'a_id');
	}

	public function selectSessionsTable(IQueryBuilder $query, string $alias = 's'): void {
		if ($alias !== '') {
			$alias .= '.';
		}

		$query->addSelect($alias . 'attendee_id')
			->addSelect($alias . 'session_id')
			->addSelect($alias . 'in_call')
			->addSelect($alias . 'last_ping')
			->selectAlias($alias . 'id', 's_id');
	}

	public function selectSessionsTableMax(IQueryBuilder $query, string $alias = 's'): void {
		if ($alias !== '') {
			$alias .= '.';
		}

		$query->selectAlias($query->func()->max($alias . 'attendee_id'), 'attendee_id')
			->selectAlias($query->func()->max($alias . 'session_id'), 'session_id')
			// BIT_OR would be better, but SQLite does not support something like it.
			->selectAlias($query->func()->max($alias . 'in_call'), 'in_call')
			->selectAlias($query->func()->max($alias . 'last_ping'), 'last_ping')
			->selectAlias($query->func()->max($alias . 'id'), 's_id');
	}
}

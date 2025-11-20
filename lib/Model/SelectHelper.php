<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\DB\QueryBuilder\IQueryBuilder;

class SelectHelper {
	public function selectRoomsTable(IQueryBuilder $query, string $alias = 'r'): void {
		if ($alias !== '') {
			$alias .= '.';
		}

		$query->addSelect([$alias . 'type',
			$alias . 'read_only',
			$alias . 'lobby_state',
			$alias . 'sip_enabled',
			$alias . 'assigned_hpb',
			$alias . 'token',
			$alias . 'name',
			$alias . 'description',
			$alias . 'password',
			$alias . 'avatar',
			$alias . 'active_since',
			$alias . 'default_permissions',
			$alias . 'call_permissions',
			$alias . 'call_flag',
			$alias . 'last_activity',
			$alias . 'last_message',
			$alias . 'lobby_timer',
			$alias . 'object_type',
			$alias . 'object_id',
			$alias . 'listable',
			$alias . 'message_expiration',
			$alias . 'remote_server',
			$alias . 'remote_token',
			$alias . 'breakout_room_mode',
			$alias . 'breakout_room_status',
			$alias . 'call_recording',
			$alias . 'recording_consent',
			$alias . 'has_federation',
			$alias . 'mention_permissions',
			$alias . 'transcription_language',
			$alias . 'last_pinned_id',
		])->selectAlias($alias . 'id', 'r_id');
	}

	public function selectAttendeesTable(IQueryBuilder $query, string $alias = 'a'): void {
		if ($alias !== '') {
			$alias .= '.';
		}

		$query->addSelect([
			$alias . 'room_id',
			$alias . 'actor_type',
			$alias . 'actor_id',
			$alias . 'display_name',
			$alias . 'pin',
			$alias . 'participant_type',
			$alias . 'favorite',
			$alias . 'notification_level',
			$alias . 'notification_calls',
			$alias . 'last_joined_call',
			$alias . 'last_read_message',
			$alias . 'last_mention_message',
			$alias . 'last_mention_direct',
			$alias . 'read_privacy',
			$alias . 'permissions',
			$alias . 'access_token',
			$alias . 'remote_id',
			$alias . 'invited_cloud_id',
			$alias . 'phone_number',
			$alias . 'call_id',
			$alias . 'state',
			$alias . 'unread_messages',
			$alias . 'last_attendee_activity',
			$alias . 'archived',
			$alias . 'important',
			$alias . 'sensitive',
			$alias . 'has_unread_threads',
			$alias . 'has_unread_thread_mentions',
			$alias . 'has_unread_thread_directs',
			$alias . 'hidden_pinned_id'
		])->selectAlias($alias . 'id', 'a_id');
	}

	public function selectSessionsTable(IQueryBuilder $query, string $alias = 's'): void {
		if ($alias !== '') {
			$alias .= '.';
		}

		$query
			->addSelect([
				$alias . 'attendee_id',
				$alias . 'session_id',
				$alias . 'in_call',
				$alias . 'last_ping',
			])
			->selectAlias($alias . 'state', 's_state')
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
			->selectAlias($query->func()->max($alias . 'state'), 's_state')
			->selectAlias($query->func()->max($alias . 'id'), 's_id');
	}
}

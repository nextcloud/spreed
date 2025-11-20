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
			->addSelect($alias . 'has_federation')
			->addSelect($alias . 'mention_permissions')
			->addSelect($alias . 'transcription_language')
			->addSelect($alias . 'last_pinned_id')
			->selectAlias($alias . 'id', 'r_id');
	}

	public function selectCommentsTable(IQueryBuilder $query, string $alias = 'c'): void {
		if ($alias !== '') {
			$alias .= '.';
		}

		$query->addSelect([
			$alias . 'parent_id AS c_parent_id',
			$alias . 'topmost_parent_id AS c_topmost_parent_id',
			$alias . 'children_count AS c_children_count',
			$alias . 'actor_type AS c_actor_type',
			$alias . 'actor_id AS c_actor_id',
			$alias . 'message AS c_message',
			$alias . 'verb AS c_verb',
			$alias . 'creation_timestamp AS c_creation_timestamp',
			$alias . 'latest_child_timestamp AS c_latest_child_timestamp',
			$alias . 'object_type AS c_object_type',
			$alias . 'object_id AS c_object_id',
			$alias . 'reference_id AS c_reference_id',
			$alias . 'reactions AS c_reactions',
			$alias . 'expire_date AS c_expire_date',
			$alias . 'meta_data AS c_meta_data',
			$alias . 'id AS c_id'
		]);
	}

	public function selectThreadsTable(IQueryBuilder $query, string $alias = 'th'): void {
		if ($alias !== '') {
			$alias .= '.';
		}

		$query->addSelect([
			$alias . 'room_id AS th_room_id',
			$alias . 'last_message_id AS th_last_message_id',
			$alias . 'num_replies AS th_num_replies',
			$alias . 'last_activity AS th_last_activity',
			$alias . 'name AS th_name',
			$alias . 'id AS th_id'
		]);
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
			->addSelect($alias . 'last_attendee_activity')
			->addSelect($alias . 'archived')
			->addSelect($alias . 'important')
			->addSelect($alias . 'sensitive')
			->addSelect($alias . 'has_unread_threads')
			->addSelect($alias . 'has_unread_thread_mentions')
			->addSelect($alias . 'has_unread_thread_directs')
			->addSelect($alias . 'hidden_pinned_id')
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

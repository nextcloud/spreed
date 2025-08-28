<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\Comments;

use OCP\Comments\IComment;
use OCP\IDBConnection;
use OCP\IUser;

class Manager implements \OCP\Comments\ICommentsManager {
	/** @var IDBConnection */
	protected $dbConn;

	protected function normalizeDatabaseData(array $data): array {
	}

	protected function cache(IComment $comment): void {
	}

	public function get($id) {
		// TODO: Implement get() method.
	}

	public function getTree($id, $limit = 0, $offset = 0) {
		// TODO: Implement getTree() method.
	}

	public function getForObject($objectType, $objectId, $limit = 0, $offset = 0, ?\DateTime $notOlderThan = null) {
		// TODO: Implement getForObject() method.
	}

	public function getForObjectSince(string $objectType, string $objectId, int $lastKnownCommentId, string $sortDirection = 'asc', int $limit = 30, bool $includeLastKnown = false): array {
		// TODO: Implement getForObjectSince() method.
	}

	public function getCommentsWithVerbForObjectSinceComment(string $objectType, string $objectId, array $verbs, int $lastKnownCommentId, string $sortDirection = 'asc', int $limit = 30, bool $includeLastKnown = false): array {
		// TODO: Implement getCommentsWithVerbForObjectSinceComment() method.
	}

	public function search(string $search, string $objectType, string $objectId, string $verb, int $offset, int $limit = 50): array {
		// TODO: Implement search() method.
	}

	public function searchForObjects(string $search, string $objectType, array $objectIds, string $verb, int $offset, int $limit = 50): array {
		// TODO: Implement searchForObjects() method.
	}

	public function getNumberOfCommentsForObject($objectType, $objectId, ?\DateTime $notOlderThan = null, $verb = '') {
		// TODO: Implement getNumberOfCommentsForObject() method.
	}

	public function getNumberOfCommentsForObjects(string $objectType, array $objectIds, ?\DateTime $notOlderThan = null, string $verb = ''): array {
		// TODO: Implement getNumberOfCommentsForObjects() method.
	}

	public function getNumberOfUnreadCommentsForObjects(string $objectType, array $objectIds, IUser $user, $verb = ''): array {
		// TODO: Implement getNumberOfUnreadCommentsForObjects() method.
	}

	public function getNumberOfCommentsForObjectSinceComment(string $objectType, string $objectId, int $lastRead, string $verb = ''): int {
		// TODO: Implement getNumberOfCommentsForObjectSinceComment() method.
	}

	public function getNumberOfCommentsWithVerbsForObjectSinceComment(string $objectType, string $objectId, int $lastRead, array $verbs): int {
		// TODO: Implement getNumberOfCommentsWithVerbsForObjectSinceComment() method.
	}

	public function getLastCommentBeforeDate(string $objectType, string $objectId, \DateTime $beforeDate, string $verb = ''): int {
		// TODO: Implement getLastCommentBeforeDate() method.
	}

	public function getLastCommentDateByActor(string $objectType, string $objectId, string $verb, string $actorType, array $actors): array {
		// TODO: Implement getLastCommentDateByActor() method.
	}

	public function getNumberOfUnreadCommentsForFolder($folderId, IUser $user) {
		// TODO: Implement getNumberOfUnreadCommentsForFolder() method.
	}

	public function create($actorType, $actorId, $objectType, $objectId) {
		// TODO: Implement create() method.
	}

	public function delete($id) {
		// TODO: Implement delete() method.
	}

	public function getReactionComment(int $parentId, string $actorType, string $actorId, string $reaction): IComment {
		// TODO: Implement getReactionComment() method.
	}

	public function retrieveAllReactions(int $parentId): array {
		// TODO: Implement retrieveAllReactions() method.
	}

	public function retrieveAllReactionsWithSpecificReaction(int $parentId, string $reaction): array {
		// TODO: Implement retrieveAllReactionsWithSpecificReaction() method.
	}

	public function supportReactions(): bool {
		// TODO: Implement supportReactions() method.
	}

	public function save(IComment $comment) {
		// TODO: Implement save() method.
	}

	public function deleteReferencesOfActor($actorType, $actorId) {
		// TODO: Implement deleteReferencesOfActor() method.
	}

	public function deleteCommentsAtObject($objectType, $objectId) {
		// TODO: Implement deleteCommentsAtObject() method.
	}

	public function setReadMark($objectType, $objectId, \DateTime $dateTime, IUser $user) {
		// TODO: Implement setReadMark() method.
	}

	public function getReadMark($objectType, $objectId, IUser $user) {
		// TODO: Implement getReadMark() method.
	}

	public function deleteReadMarksFromUser(IUser $user) {
		// TODO: Implement deleteReadMarksFromUser() method.
	}

	public function deleteReadMarksOnObject($objectType, $objectId) {
		// TODO: Implement deleteReadMarksOnObject() method.
	}

	public function registerEventHandler(\Closure $closure) {
		// TODO: Implement registerEventHandler() method.
	}

	public function registerDisplayNameResolver($type, \Closure $closure) {
		// TODO: Implement registerDisplayNameResolver() method.
	}

	public function resolveDisplayName($type, $id) {
		// TODO: Implement resolveDisplayName() method.
	}

	public function load(): void {
		// TODO: Implement load() method.
	}

	public function deleteCommentsExpiredAtObject(string $objectType, string $objectId = ''): bool {
		// TODO: Implement deleteCommentsExpiredAtObject() method.
	}
}

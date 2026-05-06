<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Exceptions\InvalidTagNameException;
use OCA\Talk\Exceptions\TagLimitExceededException;
use OCA\Talk\Exceptions\TagNameAlreadyInUseException;
use OCA\Talk\Exceptions\TagNotCustomException;
use OCA\Talk\Model\ConversationTag;
use OCA\Talk\Model\ConversationTagMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception as DBException;
use OCP\IL10N;

class ConversationTagService {
	/** Hard cap on the number of custom tags a single user can create. */
	public const MAX_CUSTOM_TAGS_PER_USER = 100;

	/** Hard cap on the number of tag IDs that can be attached to a single conversation. */
	public const MAX_TAG_IDS_PER_CONVERSATION = 20;

	/** Max length of a tag name after trimming, must fit in the VARCHAR(255) column. */
	public const MAX_TAG_NAME_LENGTH = 250;

	public function __construct(
		private ConversationTagMapper $mapper,
		private IL10N $l,
	) {
	}

	/**
	 * Load all tags for the user and insert any missing built-ins in the same pass,
	 * so callers never have to issue a separate `ensureBuiltInTags` + refetch.
	 *
	 * @return list<ConversationTag>
	 */
	private function fetchAndEnsureBuiltInTags(string $userId): array {
		$tags = $this->mapper->findByUserId($userId);

		$existingBuiltInTypes = [];
		foreach ($tags as $tag) {
			if ($tag->getType() !== ConversationTag::TYPE_CUSTOM) {
				$existingBuiltInTypes[$tag->getType()] = true;
			}
		}

		foreach ([ConversationTag::TYPE_FAVORITES, ConversationTag::TYPE_OTHER] as $type) {
			if (isset($existingBuiltInTypes[$type])) {
				continue;
			}
			$newTag = new ConversationTag();
			$newTag->setUserId($userId);
			$newTag->setName($type === ConversationTag::TYPE_FAVORITES ? $this->l->t('Favorites') : $this->l->t('Other'));
			$newTag->setType($type);
			$newTag->setCollapsed(false);
			$newTag->setSortOrder($type === ConversationTag::TYPE_FAVORITES ? 0 : 1);
			try {
				$tags[] = $this->mapper->insert($newTag);
			} catch (DBException $e) {
				// Concurrent request inserted the same built-in already. The row exists,
				// but we don't have a reference to it in $tags; worst case the caller sees
				// a sort_order collision that gets resolved on the next reorder. Safe to skip.
				if ($e->getReason() !== DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $e;
				}
			}
		}

		return $tags;
	}

	/**
	 * @return list<ConversationTag>
	 */
	public function getTags(string $userId): array {
		return $this->fetchAndEnsureBuiltInTags($userId);
	}

	public function getTag(string $tagId, string $userId): ConversationTag {
		return $this->mapper->findById($tagId, $userId);
	}

	/**
	 * @throws InvalidTagNameException when the name is empty or exceeds MAX_TAG_NAME_LENGTH
	 * @throws TagLimitExceededException when the user already owns MAX_CUSTOM_TAGS_PER_USER custom tags
	 * @throws TagNameAlreadyInUseException when the user already owns a custom tag with the same name
	 */
	public function createTag(string $userId, string $name): ConversationTag {
		$name = $this->normalizeTagName($name);

		// Single read: we derive the custom count, the max custom sort_order, and the
		// current 'other' built-in from the one list we just fetched.
		$tags = $this->fetchAndEnsureBuiltInTags($userId);

		$customCount = 0;
		$maxCustomSortOrder = 0;
		$otherBuiltIn = null;
		foreach ($tags as $tag) {
			if ($tag->getType() === ConversationTag::TYPE_CUSTOM) {
				$customCount++;
				if ($tag->getSortOrder() > $maxCustomSortOrder) {
					$maxCustomSortOrder = $tag->getSortOrder();
				}
			} elseif ($tag->getType() === ConversationTag::TYPE_OTHER) {
				$otherBuiltIn = $tag;
			}
		}

		if ($customCount >= self::MAX_CUSTOM_TAGS_PER_USER) {
			throw new TagLimitExceededException();
		}

		$newSortOrder = $maxCustomSortOrder + 1;

		// If 'other' sits exactly at the new sort_order, push it up by one to keep sort_orders unique.
		if ($otherBuiltIn !== null && $otherBuiltIn->getSortOrder() === $newSortOrder) {
			$otherBuiltIn->setSortOrder($newSortOrder + 1);
			$this->mapper->update($otherBuiltIn);
		}

		$newTag = new ConversationTag();
		$newTag->setUserId($userId);
		$newTag->setName($name);
		$newTag->setType(ConversationTag::TYPE_CUSTOM);
		$newTag->setSortOrder($newSortOrder);
		$newTag->setCollapsed(false);

		try {
			return $this->mapper->insert($newTag);
		} catch (DBException $e) {
			if ($e->getReason() === DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw new TagNameAlreadyInUseException();
			}
			throw $e;
		}
	}

	/**
	 * @throws DoesNotExistException when no tag with that id exists for the user
	 * @throws InvalidTagNameException when the name is empty or exceeds MAX_TAG_NAME_LENGTH
	 * @throws TagNotCustomException when the tag is a built-in (favorites/other) and therefore immutable
	 * @throws TagNameAlreadyInUseException when the user already owns another custom tag with the same name
	 */
	public function updateTag(string $tagId, string $userId, string $name): ConversationTag {
		$name = $this->normalizeTagName($name);
		$tag = $this->mapper->findById($tagId, $userId);
		if ($tag->getType() !== ConversationTag::TYPE_CUSTOM) {
			throw new TagNotCustomException();
		}
		$tag->setName($name);
		try {
			return $this->mapper->update($tag);
		} catch (DBException $e) {
			if ($e->getReason() === DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw new TagNameAlreadyInUseException();
			}
			throw $e;
		}
	}

	/**
	 * @throws DoesNotExistException when no tag with that id exists for the user
	 * @throws TagNotCustomException when the tag is a built-in (favorites/other) and cannot be deleted
	 */
	public function deleteTag(string $tagId, string $userId): void {
		$tag = $this->mapper->findById($tagId, $userId);
		if ($tag->getType() !== ConversationTag::TYPE_CUSTOM) {
			throw new TagNotCustomException();
		}
		$this->mapper->clearTagFromAttendees($tagId, $userId);
		$this->mapper->delete($tag);
	}

	/**
	 * Trim the caller-supplied name, enforce non-empty and the MAX_TAG_NAME_LENGTH cap.
	 *
	 * @throws InvalidTagNameException
	 */
	private function normalizeTagName(string $name): string {
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > self::MAX_TAG_NAME_LENGTH) {
			throw new InvalidTagNameException();
		}
		return $name;
	}

	/**
	 * Take a caller-supplied list of tag IDs and return the subset that is:
	 *  - a numeric string (matches `^\d+$`),
	 *  - owned by $userId in the tag table,
	 *  - not a duplicate,
	 *  - within the MAX_TAG_IDS_PER_CONVERSATION cap.
	 *
	 * Everything else is silently dropped. The return value can safely be persisted as-is.
	 *
	 * @param list<mixed> $tagIds Unchecked input from the API layer
	 * @return list<string>
	 */
	public function validateTagIdsForUser(string $userId, array $tagIds): array {
		if ($tagIds === []) {
			return [];
		}

		$ownedIds = array_map(
			static fn (ConversationTag $tag): string => (string)$tag->getId(),
			$this->mapper->findByUserId($userId),
		);

		$valid = [];
		foreach ($tagIds as $tagId) {
			if (count($valid) >= self::MAX_TAG_IDS_PER_CONVERSATION) {
				break;
			}
			if (!is_string($tagId) && !is_int($tagId)) {
				continue;
			}
			$tagIdStr = (string)$tagId;
			if (!preg_match('/^\d+$/', $tagIdStr)) {
				continue;
			}
			if (!in_array($tagIdStr, $ownedIds, true)) {
				continue;
			}
			$valid[] = $tagIdStr;
		}
		return array_values(array_unique($valid));
	}

	/**
	 * @param string[] $orderedIds
	 */
	public function reorderTags(string $userId, array $orderedIds): void {
		$tags = $this->mapper->findByUserId($userId);

		$order = 0;
		$seen = [];
		foreach ($orderedIds as $id) {
			if (!is_string($id) || isset($seen[$id])) {
				continue;
			}
			foreach ($tags as $tag) {
				if ($tag->getId() !== $id) {
					continue;
				}
				$tag->setSortOrder($order);
				$this->mapper->update($tag);
				$order++;
				$seen[$id] = true;
				break;
			}
		}
	}

	/**
	 * Set the collapsed state of a tag
	 *
	 * @throws DoesNotExistException
	 */
	public function setCollapsed(string $tagId, string $userId, bool $collapsed): ConversationTag {
		$tag = $this->mapper->findById($tagId, $userId);
		$tag->setCollapsed($collapsed);
		return $this->mapper->update($tag);
	}
}

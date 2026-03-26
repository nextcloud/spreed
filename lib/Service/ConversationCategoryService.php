<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Model\ConversationCategory;
use OCA\Talk\Model\ConversationCategoryMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class ConversationCategoryService {
	public function __construct(
		private ConversationCategoryMapper $mapper,
	) {
	}

	/**
	 * Ensure the built-in 'favorites' and 'other' categories exist for the user.
	 * They are created with sortOrders that place favorites before and other after custom categories by default.
	 */
	private function ensureBuiltInCategories(string $userId): void {
		foreach ([ConversationCategory::TYPE_FAVORITES, ConversationCategory::TYPE_OTHER] as $type) {
			try {
				$this->mapper->findBuiltInByType($userId, $type);
			} catch (DoesNotExistException) {
				$category = new ConversationCategory();
				$category->setUserId($userId);
				$category->setName($type);
				$category->setType($type);
				$category->setCollapsed(false);
				$category->setSortOrder($type === ConversationCategory::TYPE_FAVORITES ? 0 : 1);
				$this->mapper->insert($category);
			}
		}
	}

	/**
	 * @return ConversationCategory[]
	 */
	public function getCategories(string $userId): array {
		$this->ensureBuiltInCategories($userId);
		return $this->mapper->findByUserId($userId);
	}

	public function getCategory(int $categoryId, string $userId): ConversationCategory {
		return $this->mapper->findById($categoryId, $userId);
	}

	public function createCategory(string $userId, string $name): ConversationCategory {
		$maxOrder = $this->mapper->getMaxSortOrder($userId);
		$newSortOrder = $maxOrder + 1;

		// If 'other' sits exactly at the new sort_order, push it up by one to keep sort_orders unique
		try {
			$other = $this->mapper->findBuiltInByType($userId, ConversationCategory::TYPE_OTHER);
			if ($other->getSortOrder() === $newSortOrder) {
				$other->setSortOrder($newSortOrder + 1);
				$this->mapper->update($other);
			}
		} catch (DoesNotExistException) {
		}

		$category = new ConversationCategory();
		$category->setUserId($userId);
		$category->setName($name);
		$category->setType(ConversationCategory::TYPE_CUSTOM);
		$category->setSortOrder($newSortOrder);
		$category->setCollapsed(false);

		return $this->mapper->insert($category);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function updateCategory(int $categoryId, string $userId, string $name): ConversationCategory {
		$category = $this->mapper->findById($categoryId, $userId);
		$category->setName($name);
		return $this->mapper->update($category);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function deleteCategory(int $categoryId, string $userId): void {
		$category = $this->mapper->findById($categoryId, $userId);
		$this->mapper->clearCategoryFromAttendees($categoryId, $userId);
		$this->mapper->delete($category);
	}

	/**
	 * @param string[] $orderedIds
	 */
	public function reorderCategories(string $userId, array $orderedIds): void {
		$categories = $this->mapper->findByUserId($userId);
		$categoryMap = [];
		foreach ($categories as $category) {
			$categoryMap[$category->getId()] = $category;
		}

		$order = 0;
		foreach ($orderedIds as $id) {
			if (!isset($categoryMap[$id])) {
				continue;
			}
			$category = $categoryMap[$id];
			$category->setSortOrder($order);
			$this->mapper->update($category);
			$order++;
		}
	}

	/**
	 * Set the collapsed state of a category
	 *
	 * @throws DoesNotExistException
	 */
	public function setCollapsed(int $categoryId, string $userId, bool $collapsed): ConversationCategory {
		$category = $this->mapper->findById($categoryId, $userId);
		$category->setCollapsed($collapsed);
		return $this->mapper->update($category);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function toggleCollapsed(int $categoryId, string $userId): ConversationCategory {
		$category = $this->mapper->findById($categoryId, $userId);
		$category->setCollapsed(!$category->isCollapsed());
		return $this->mapper->update($category);
	}
}

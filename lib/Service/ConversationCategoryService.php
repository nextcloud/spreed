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
	 * @return ConversationCategory[]
	 */
	public function getCategories(string $userId): array {
		return $this->mapper->findByUserId($userId);
	}

	public function getCategory(int $categoryId, string $userId): ConversationCategory {
		return $this->mapper->findById($categoryId, $userId);
	}

	public function createCategory(string $userId, string $name): ConversationCategory {
		$maxOrder = $this->mapper->getMaxSortOrder($userId);

		$category = new ConversationCategory();
		$category->setUserId($userId);
		$category->setName($name);
		$category->setSortOrder($maxOrder + 1);
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
	 * @param int[] $orderedIds
	 * @throws DoesNotExistException
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
	 * @throws DoesNotExistException
	 */
	public function toggleCollapsed(int $categoryId, string $userId): ConversationCategory {
		$category = $this->mapper->findById($categoryId, $userId);
		$category->setCollapsed(!$category->isCollapsed());
		return $this->mapper->update($category);
	}
}

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Model\ConversationSection;
use OCA\Talk\Model\ConversationSectionMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class ConversationSectionService {
	public function __construct(
		private ConversationSectionMapper $mapper,
	) {
	}

	/**
	 * @return ConversationSection[]
	 */
	public function getSections(string $userId): array {
		return $this->mapper->findByUserId($userId);
	}

	public function getSection(int $sectionId): ConversationSection {
		return $this->mapper->findById($sectionId);
	}

	public function createSection(string $userId, string $name): ConversationSection {
		$maxOrder = $this->mapper->getMaxSortOrder($userId);

		$section = new ConversationSection();
		$section->setUserId($userId);
		$section->setName($name);
		$section->setSortOrder($maxOrder + 1);
		$section->setCollapsed(false);

		return $this->mapper->insert($section);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function updateSection(int $sectionId, string $userId, string $name): ConversationSection {
		$section = $this->mapper->findById($sectionId);
		if ($section->getUserId() !== $userId) {
			throw new DoesNotExistException('Section not found');
		}

		$section->setName($name);
		return $this->mapper->update($section);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function deleteSection(int $sectionId, string $userId): void {
		$section = $this->mapper->findById($sectionId);
		if ($section->getUserId() !== $userId) {
			throw new DoesNotExistException('Section not found');
		}

		$this->mapper->clearSectionFromAttendees($sectionId);
		$this->mapper->delete($section);
	}

	/**
	 * @param int[] $orderedIds
	 * @throws DoesNotExistException
	 */
	public function reorderSections(string $userId, array $orderedIds): void {
		$sections = $this->mapper->findByUserId($userId);
		$sectionMap = [];
		foreach ($sections as $section) {
			$sectionMap[$section->getId()] = $section;
		}

		$order = 0;
		foreach ($orderedIds as $id) {
			if (!isset($sectionMap[$id])) {
				continue;
			}
			$section = $sectionMap[$id];
			$section->setSortOrder($order);
			$this->mapper->update($section);
			$order++;
		}
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function toggleCollapsed(int $sectionId, string $userId): ConversationSection {
		$section = $this->mapper->findById($sectionId);
		if ($section->getUserId() !== $userId) {
			throw new DoesNotExistException('Section not found');
		}

		$section->setCollapsed(!$section->isCollapsed());
		return $this->mapper->update($section);
	}
}

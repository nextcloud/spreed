<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat\AutoComplete;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\CommentsManager;
use OCP\Collaboration\AutoComplete\ISorter;

class Sorter implements ISorter {
	public function __construct(
		protected CommentsManager $commentsManager,
	) {
	}

	/**
	 * @return string The ID of the sorter, e.g. commenters
	 * @since 13.0.0
	 */
	#[\Override]
	public function getId(): string {
		return 'talk_chat_participants';
	}

	/**
	 * executes the sort action
	 *
	 * @param array $sortArray the array to be sorted, provided as reference
	 * @param array{itemType: string, itemId: string, search?: string, selfUserId?: ?string, selfCloudId?: ?string} $context carries key 'itemType' and 'itemId' of the source object (e.g. a file)
	 * @since 13.0.0
	 */
	#[\Override]
	public function sort(array &$sortArray, array $context): void {
		foreach ($sortArray as $type => &$byType) {
			if ($type !== 'users') {
				continue;
			}

			/** @var \DateTime[] $lastComments */
			$lastComments = $this->commentsManager->getLastCommentDateByActor(
				$context['itemType'],
				$context['itemId'],
				ChatManager::VERB_MESSAGE,
				$type,
				array_map(function (array $suggestion) {
					return $suggestion['value']['shareWith'];
				}, $byType));

			$search = $context['search'];
			$selfUserId = $context['selfUserId'] ?? null;

			usort($byType, static function (array $a, array $b) use ($lastComments, $search, $selfUserId) {
				if ($selfUserId === $a['value']['shareWith']) {
					return 1;
				}
				if ($selfUserId === $b['value']['shareWith']) {
					return -1;
				}

				if ($search) {
					// If the user searched for "Dani" we make sure "Daniel" comes before "Madani"
					if (stripos($a['label'], $search) === 0) {
						if (stripos($b['label'], $search) !== 0) {
							return -1;
						}
					} elseif (stripos($b['label'], $search) === 0) {
						return 1;
					}
				}

				if (!isset($lastComments[$b['value']['shareWith']])) {
					return -1;
				}
				if (!isset($lastComments[$a['value']['shareWith']])) {
					return 1;
				}
				return $lastComments[$b['value']['shareWith']]->getTimestamp() - $lastComments[$a['value']['shareWith']]->getTimestamp();
			});
		}
	}
}

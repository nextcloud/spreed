<?php
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Chat\AutoComplete;


use OCA\Spreed\Chat\CommentsManager;
use OCP\Collaboration\AutoComplete\ISorter;

class Sorter implements ISorter {

	/** @var CommentsManager */
	protected $commentsManager;

	public function __construct(CommentsManager $commentsManager) {
		$this->commentsManager = $commentsManager;
	}

	/**
	 * @return string The ID of the sorter, e.g. commenters
	 * @since 13.0.0
	 */
	public function getId() {
		return 'talk_chat_participants';
	}

	/**
	 * executes the sort action
	 *
	 * @param array $sortArray the array to be sorted, provided as reference
	 * @param array $context carries key 'itemType' and 'itemId' of the source object (e.g. a file)
	 * @since 13.0.0
	 */
	public function sort(array &$sortArray, array $context) {
		foreach ($sortArray as $type => &$byType) {
			$lastComments = $this->commentsManager->getLastCommentDateByActor(
				$context['itemType'],
				$context['itemId'],
				'comment',
				$type,
				array_map(function($suggestion) {
					return $suggestion['value']['shareWith'];
			}, $byType));

			usort($byType, function ($a, $b) use ($lastComments) {
				if (!isset($lastComments[$b['value']['shareWith']])) {
					return -1;
				}
				if (!isset($lastComments[$a['value']['shareWith']])) {
					return 1;
				}
				return $lastComments[$a['value']['shareWith']] - $lastComments[$b['value']['shareWith']];
			});
		}
	}
}

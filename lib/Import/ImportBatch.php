<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

use OCP\Comments\IComment;

/**
 * What one import call touched, so derived values (child counts, reaction
 * summaries, thread counters) are updated once per call instead of once
 * per message
 *
 * @internal
 */
final class ImportBatch {
	/** @var array<int, IComment> comments written in this batch by ID */
	public array $comments = [];
	/** @var array<int, true> comments whose child information changed */
	public array $parents = [];
	/** @var array<int, true> comments whose reactions changed */
	public array $reactionParents = [];
	/** @var array<int, true> threads with new replies */
	public array $threads = [];
	/** @var array<int, true> threads started in this batch */
	public array $threadRoots = [];
	/** @var array<int, ImportRef> share refs by file ID */
	public array $shares = [];
}

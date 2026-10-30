<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

/**
 * Display names of placeholder actors ({@see \OCA\Talk\Model\Attendee::ACTOR_IMPORTED})
 */
class ImportedActorDirectory {
	/** @var array<string, string> */
	private array $names = [];

	public function __construct(
		private readonly ImportRefMapper $mapper,
	) {
	}

	/**
	 * @return string the name the source showed, '' when the actor is unknown
	 */
	public function getDisplayName(string $actorId): string {
		if (!isset($this->names[$actorId])) {
			$this->names += $this->mapper->getActorDisplayNames([$actorId]);
			$this->names[$actorId] ??= '';
		}
		return $this->names[$actorId];
	}

	public function setDisplayName(string $actorId, string $displayName): void {
		$this->names[$actorId] = $displayName;
	}
}

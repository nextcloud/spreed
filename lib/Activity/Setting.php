<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Activity;

use OCP\Activity\ActivitySettings;
use OCP\IL10N;

class Setting extends ActivitySettings {

	public function __construct(
		protected IL10N $l,
	) {
	}

	/**
	 * @return string Lowercase a-z and underscore only identifier
	 * @since 11.0.0
	 */
	#[\Override]
	public function getIdentifier(): string {
		return 'spreed';
	}

	/**
	 * @return string A translated string
	 * @since 11.0.0
	 */
	#[\Override]
	public function getName(): string {
		return $this->l->t('You were invited to a <strong>conversation</strong> or had a <strong>call</strong>');
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getGroupIdentifier(): string {
		return 'other';
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getGroupName(): string {
		return $this->l->t('Other activities');
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getPriority(): int {
		return 51;
	}
	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function canChangeNotification(): bool {
		return false;
	}
	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function isDefaultEnabledNotification(): bool {
		return false;
	}
}

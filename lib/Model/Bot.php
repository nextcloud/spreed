<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

class Bot {
	public const STATE_DISABLED = 0;
	public const STATE_ENABLED = 1;
	public const STATE_NO_SETUP = 2;

	public const FEATURE_NONE = 0;
	public const FEATURE_WEBHOOK = 1;
	public const FEATURE_RESPONSE = 2;
	public const FEATURE_EVENT = 4;
	public const FEATURE_REACTION = 8;

	public const FEATURE_LABEL_NONE = 'none';
	public const FEATURE_LABEL_WEBHOOK = 'webhook';
	public const FEATURE_LABEL_RESPONSE = 'response';
	public const FEATURE_LABEL_EVENT = 'event';
	public const FEATURE_LABEL_REACTION = 'reaction';
	public const URL_APP_PREFIX = 'nextcloudapp://';
	public const URL_RESPONSE_ONLY_PREFIX = 'responseonly://';

	public const FEATURE_MAP = [
		self::FEATURE_NONE => self::FEATURE_LABEL_NONE,
		self::FEATURE_WEBHOOK => self::FEATURE_LABEL_WEBHOOK,
		self::FEATURE_RESPONSE => self::FEATURE_LABEL_RESPONSE,
		self::FEATURE_EVENT => self::FEATURE_LABEL_EVENT,
		self::FEATURE_REACTION => self::FEATURE_LABEL_REACTION,
	];

	public function __construct(
		protected BotServer $botServer,
		protected BotConversation $botConversation,
	) {
	}

	public function getBotServer(): BotServer {
		return $this->botServer;
	}

	public function getBotConversation(): BotConversation {
		return $this->botConversation;
	}

	public function isEnabled(): bool {
		return $this->botServer->getState() !== self::STATE_DISABLED
			&& $this->botConversation->getState() !== self::STATE_DISABLED;
	}

	public static function featureFlagsToLabels(int $flags): string {
		if ($flags === self::FEATURE_NONE) {
			return self::FEATURE_LABEL_NONE;
		}

		$features = [];
		foreach (self::FEATURE_MAP as $flag => $label) {
			if ($flags & $flag) {
				$features[] = $label;
			}
		}
		return implode(', ', $features);
	}

	public static function featureLabelsToFlags(array $labels): int {
		$reverseMap = array_flip(self::FEATURE_MAP);
		$flags = 0;
		foreach ($labels as $label) {
			if ($label === self::FEATURE_LABEL_NONE) {
				return self::FEATURE_NONE;
			}
			if (isset($reverseMap[$label])) {
				$flags += $reverseMap[$label];
			}
		}

		return $flags;
	}
}

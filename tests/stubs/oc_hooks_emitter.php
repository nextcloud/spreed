<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\Hooks {
	class Emitter {
		public function emit(string $class, string $value, array $option) {
		}
		/** Closure $closure */
		public function listen(string $class, string $value, $closure) {
		}
	}
}

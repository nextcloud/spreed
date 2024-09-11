<?php
/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Memcache {

	use OCP\IMemcache;

	class NullCache implements IMemcache {
		public function add($key, $value, $ttl = 0) {
		}

		public function inc($key, $step = 1) {
		}

		public function dec($key, $step = 1) {
		}

		public function cas($key, $old, $new) {
		}

		public function cad($key, $old) {
		}

		public function ncad(string $key, mixed $old): bool {
		}
	}

	class ArrayCache implements IMemcache {
		public function add($key, $value, $ttl = 0) {
		}

		public function inc($key, $step = 1) {
		}

		public function dec($key, $step = 1) {
		}

		public function cas($key, $old, $new) {
		}

		public function cad($key, $old) {
		}

		public function ncad(string $key, mixed $old): bool {
		}
	}
}

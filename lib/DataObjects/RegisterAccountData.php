<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\DataObjects;

class RegisterAccountData {

	public function __construct(
		private string $url,
		private string $name,
		private string $email,
		private string $language,
		private string $country,
	) {
	}

	public function getUrl(): string {
		return $this->url;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getEmail(): string {
		return $this->email;
	}

	public function getLanguage(): string {
		return $this->language;
	}

	public function getCountry(): string {
		return $this->country;
	}
}

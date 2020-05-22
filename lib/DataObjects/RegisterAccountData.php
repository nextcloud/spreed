<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Morris Jobke <hey@morrisjobke.de>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Talk\DataObjects;

class RegisterAccountData {
	/** @var string */
	private $url;
	/** @var string */
	private $name;
	/** @var string */
	private $email;
	/** @var string */
	private $language;
	/** @var string */
	private $country;

	public function __construct(string $url, string $name, string $email, string $language, string $country) {
		$this->url = $url;
		$this->name = $name;
		$this->email = $email;
		$this->language = $language;
		$this->country = $country;
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

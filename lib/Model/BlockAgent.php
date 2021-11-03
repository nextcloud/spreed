<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021, Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method void setBlockedType(string $blockedType)
 * @method string getBlockedType()
 * @method void setBlockedId(string $blockedId)
 * @method string getBlockedId()
 * @method void setDatetime(\DateTime $datetime)
 * @method \DateTime getDatetime()
 */
class BlockAgent extends Entity {
	/** @var string */
	protected $actorType;
	/** @var string */
	protected $actorId;
	/** @var string */
	protected $blockedType;
	/** @var string */
	protected $blockedId;
	/** @var \DateTime */
	protected $datetime;
	public function __construct() {
		$this->addType('actorType', 'string');
		$this->addType('actorId', 'string');
		$this->addType('blockedType', 'string');
		$this->addType('blockedId', 'string');
		$this->addType('datetime', 'datetime');
	}

	public function setDatetime($datetime): void {
		if (!$datetime instanceof \DateTime) {
			$datetime = new \DateTime($datetime);
		}
		$this->datetime = $datetime;
		$this->markFieldUpdated('datetime');
	}
}

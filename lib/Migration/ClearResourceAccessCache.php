<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Migration;

use OCA\Talk\Collaboration\Resources\ConversationProvider;
use OCP\Collaboration\Resources\IManager;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class ClearResourceAccessCache implements IRepairStep {
	protected const INVALIDATIONS = 1;

	/** @var IConfig */
	protected $config;
	/** @var IManager */
	protected $manager;
	/** @var ConversationProvider */
	protected $provider;

	public function __construct(IConfig $config,
								IManager $manager,
								ConversationProvider $provider) {
		$this->config = $config;
		$this->manager = $manager;
		$this->provider = $provider;
	}

	public function getName(): string {
		return 'Invalidate access cache for projects conversation provider';
	}

	public function run(IOutput $output): void {
		$invalidatedCache = (int) $this->config->getAppValue('spreed', 'project_access_invalidated', '0');

		if ($invalidatedCache === self::INVALIDATIONS) {
			$output->info('Invalidation not required');
			return;
		}

		$this->manager->invalidateAccessCacheForProvider($this->provider);
		$this->config->setAppValue('spreed', 'project_access_invalidated', (string) self::INVALIDATIONS);
	}
}

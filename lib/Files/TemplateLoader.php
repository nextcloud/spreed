<?php
declare(strict_types=1);

/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Spreed\Files;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Helper class to add the Talk UI to the sidebar of the Files app.
 */
class TemplateLoader {

	/** @var EventDispatcherInterface */
	protected $dispatcher;

	public function __construct(EventDispatcherInterface $dispatcher) {
		$this->dispatcher = $dispatcher;
	}

	public function register() {
		$listener = function() {
			$this->loadTalkSidebarForFilesApp();
		};
		$this->dispatcher->addListener('OCA\Files::loadAdditionalScripts', $listener);
	}

	/**
	 * Loads the Talk UI in the sidebar of the Files app.
	 *
	 * This method should be called when loading additional scripts for the
	 * Files app.
	 */
	public function loadTalkSidebarForFilesApp() {
		\OCP\Util::addStyle('spreed', 'merged-files');
		\OCP\Util::addScript('spreed', 'merged-files');
	}

}

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

use OCP\Util;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

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
		Util::addStyle('spreed', 'files');
		Util::addStyle('spreed', 'chatview');
		Util::addStyle('spreed', 'autocomplete');

		Util::addScript('spreed', 'vendor/backbone/backbone-min');
		Util::addScript('spreed', 'vendor/backbone.radio/build/backbone.radio.min');
		Util::addScript('spreed', 'vendor/backbone.marionette/lib/backbone.marionette.min');
		Util::addScript('spreed', 'vendor/jshashes/hashes.min');
		Util::addScript('spreed', 'vendor/Caret.js/dist/jquery.caret.min');
		Util::addScript('spreed', 'vendor/At.js/dist/js/jquery.atwho.min');
		Util::addScript('spreed', 'models/chatmessage');
		Util::addScript('spreed', 'models/chatmessagecollection');
		Util::addScript('spreed', 'models/room');
		Util::addScript('spreed', 'models/roomcollection');
		Util::addScript('spreed', 'views/chatview');
		Util::addScript('spreed', 'views/editabletextlabel');
		Util::addScript('spreed', 'views/richobjectstringparser');
		Util::addScript('spreed', 'views/templates');
		Util::addScript('spreed', 'views/virtuallist');
		Util::addScript('spreed', 'signaling');
		Util::addScript('spreed', 'connection');
		Util::addScript('spreed', 'embedded');
		Util::addScript('spreed', 'filesplugin');
	}

}

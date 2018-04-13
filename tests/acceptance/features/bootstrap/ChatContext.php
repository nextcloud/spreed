<?php

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

use Behat\Behat\Context\Context;

class ChatContext implements Context, ActorAwareInterface {

	/**
	 * @var Actor
	 */
	private $actor;

	/**
	 * @var array
	 */
	private $chatAncestorsByActor;

	/**
	 * @var Locator
	 */
	private $chatAncestor;

	/**
	 * @BeforeScenario
	 */
	public function initializeChatAncestors() {
		$this->chatAncestorsByActor = array();
		$this->chatAncestor = null;
	}

	/**
	 * @param Actor $actor
	 */
	public function setCurrentActor(Actor $actor) {
		$this->actor = $actor;

		if (array_key_exists($actor->getName(), $this->chatAncestorsByActor)) {
			$this->chatAncestor = $this->chatAncestorsByActor[$actor->getName()];
		} else {
			$this->chatAncestor = null;
		}
	}

	/**
	 * Sets the chat ancestor to be used in the steps performed by the given
	 * actor from that point on (until changed again).
	 *
	 * This is meant to be called from other contexts, for example, when the
	 * user joins or leaves a video call.
	 *
	 * The ChatAncestorSetter trait can be used to reduce the boilerplate needed
	 * to set the chat ancestor from other contexts.
	 *
	 * @param null|Locator $chatAncestor the chat ancestor
	 * @param Actor $actor the actor
	 */
	public function setChatAncestorForActor($chatAncestor, Actor $actor) {
		$this->chatAncestorsByActor[$actor->getName()] = $chatAncestor;
	}

	/**
	 * @return Locator
	 */
	public static function chatView($chatAncestor) {
		return Locator::forThe()->css(".chat")->
				descendantOf($chatAncestor)->
				describedAs("Chat view in Talk app");
	}

	/**
	 * @Then I see that the chat is shown in the main view
	 */
	public function iSeeThatTheChatIsShownInTheMainView() {
		PHPUnit_Framework_Assert::assertTrue($this->actor->find(self::chatView($this->chatAncestor), 10)->isVisible());
	}

}

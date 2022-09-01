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

use Behat\Behat\Hook\Scope\BeforeScenarioScope;

/**
 * Helper trait to set the ancestor of the chat.
 *
 * The ChatContext provides steps to interact with and check the behaviour of
 * the chat view. However, the ChatContext does not know the right chat ancestor
 * that has to be used by the chat steps; this has to be set from other
 * contexts, for example, when the user joins or leaves a call.
 *
 * Contexts that "know" that certain chat ancestor has to be used by the
 * ChatContext steps should use this trait and call "setChatAncestorForActor"
 * when needed.
 */
trait ChatAncestorSetter {
	/**
	 * @var ChatContext
	 */
	private $chatContext;

	/**
	 * @BeforeScenario
	 */
	public function getSiblingChatContext(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->chatContext = $environment->getContext("ChatContext");
	}

	/**
	 * Sets the chat ancestor to be used in the chat steps performed by the
	 * given actor.
	 *
	 * @param null|Locator $chatAncestor the chat ancestor
	 * @param Actor $actor the actor
	 */
	private function setChatAncestorForActor($chatAncestor, Actor $actor) {
		$this->chatContext->setChatAncestorForActor($chatAncestor, $actor);
	}
}

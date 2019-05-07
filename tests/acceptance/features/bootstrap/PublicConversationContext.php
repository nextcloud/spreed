<?php

/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

class PublicConversationContext implements Context, ActorAwareInterface {

	use ActorAware;
	use ChatAncestorSetter;

	/**
	 * @var ChatContext
	 */
	private $chatContext;

	/**
	 * @BeforeScenario
	 */
	public function getOtherRequiredSiblingContexts(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->chatContext = $environment->getContext("ChatContext");
	}

	/**
	 * @When I visit the public conversation link I wrote down
	 */
	public function iVisitThePublicConversationLinkIWroteDown() {
		$this->actor->getSession()->visit($this->actor->getSharedNotebook()["public conversation link"]);
	}

	/**
	 * @Then I see that the current page is the public conversation link I wrote down
	 */
	public function iSeeThatTheCurrentPageIsThePublicConversationLinkIWroteDown() {
		PHPUnit_Framework_Assert::assertEquals(
				$this->actor->getSharedNotebook()["public conversation link"],
				$this->actor->getSession()->getCurrentUrl());

		$this->setChatAncestorForActor(TalkAppContext::mainView(), $this->actor);

		// The authenticate page for shared links in Files app has a special
		// URL, but the authenticate page for public conversations does not, so
		// it needs to be checked too that the chat view is shown.
		$this->chatContext->iSeeThatTheChatIsShownInTheMainView();
	}

}

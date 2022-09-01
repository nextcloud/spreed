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
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

class FilesAppChatTabContext implements Context, ActorAwareInterface {
	use ActorAware;
	use ChatAncestorSetter;

	/**
	 * @var FilesAppContext
	 */
	private $filesAppContext;

	/**
	 * @return Locator
	 */
	public static function emptyContent() {
		return Locator::forThe()->css(".emptycontent")->
				descendantOf(FilesAppContext::tabInDetailsViewNamed("Chat"))->
				describedAs("Empty content in tab named Chat in details view in Files app");
	}

	/**
	 * @BeforeScenario
	 */
	public function getOtherRequiredSiblingContexts(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->filesAppContext = $environment->getContext("FilesAppContext");
	}

	// "of the Files app" is needed to resolve the ambiguity between this step
	// and the one defined in FilesAppContext.
	/**
	 * @Given I open the Chat tab in the details view of the Files app
	 */
	public function iOpenTheChatTabInTheDetailsViewOfTheFilesApp() {
		$this->filesAppContext->iOpenTheTabInTheDetailsView("Chat");

		$this->setChatAncestorForActor(FilesAppContext::tabInDetailsViewNamed("Chat"), $this->actor);
	}

	/**
	 * @Then I see that the Chat tab header is not shown in the details view
	 */
	public function iSeeThatTheChatTabHeaderIsNotShownInTheDetailsView() {
		if (!WaitFor::elementToBeEventuallyNotShown(
			$this->actor,
			FilesAppContext::tabHeaderInDetailsViewNamed("Chat"),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The tab header named Chat is still shown in the details view after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the :text empty content message is shown in the chat tab
	 */
	public function iSeeThatTheEmptyContentMessageIsShownInTheChatTab($text) {
		PHPUnit_Framework_Assert::assertEquals($text, $this->actor->find(self::emptyContent(), 10)->getText());
	}

	/**
	 * @Then I see that the chat is shown in the Chat tab
	 */
	public function iSeeThatTheChatIsShownInTheChatTab() {
		// The chat may be present in the DOM but hidden behind a loading icon
		// while the messages are being loaded, so it has to be explicitly
		// waited for it to be visible instead of relying on the implicit wait
		// made to find the element.
		if (!WaitFor::elementToBeEventuallyShown(
			$this->actor,
			ChatContext::chatView(FilesAppContext::tabInDetailsViewNamed("Chat")),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The chat was not shown yet in the chat tab after $timeout seconds");
		}
	}
}

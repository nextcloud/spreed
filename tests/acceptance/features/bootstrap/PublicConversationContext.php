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
	 * @return Locator
	 */
	public static function passwordProtectedConversationWarning() {
		return Locator::forThe()->xpath("//*[@class = 'warning-info' and normalize-space() = 'This conversation is password-protected']")->
				describedAs("Password protected conversation warning in Authenticate page");
	}

	/**
	 * @return Locator
	 */
	public static function passwordField() {
		return Locator::forThe()->field("password")->
				describedAs("Password field in Authenticate page");
	}

	/**
	 * @return Locator
	 */
	public static function authenticateButton() {
		return Locator::forThe()->id("password-submit")->
				describedAs("Authenticate button in Authenticate page");
	}

	/**
	 * @return Locator
	 */
	public static function wrongPasswordMessage() {
		return Locator::forThe()->xpath("//*[@class = 'warning' and normalize-space() = 'The password is wrong. Try again.']")->
				describedAs("Wrong password message in Authenticate page");
	}

	/**
	 * @When I visit the public conversation link I wrote down
	 */
	public function iVisitThePublicConversationLinkIWroteDown() {
		$this->actor->getSession()->visit($this->actor->getSharedNotebook()["public conversation link"]);
	}

	/**
	 * @When I authenticate with password :password in public conversation
	 */
	public function iAuthenticateWithPasswordInPublicConversation($password) {
		$this->actor->find(self::passwordField(), 10)->setValue($password);
		$this->actor->find(self::authenticateButton())->click();
	}

	/**
	 * @Then I see that the current page is the Authenticate page for the public conversation link I wrote down
	 */
	public function iSeeThatTheCurrentPageIsTheAuthenticatePageForThePublicConversationLinkIWroteDown() {
		// The authenticate page for shared links in Files app has a special
		// URL, but the authenticate page for public conversations does not, so
		// it needs to be checked that the warning is shown instead.
		if (!WaitFor::elementToBeEventuallyShown(
			$this->actor,
			self::passwordProtectedConversationWarning(),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The password protected conversation warning was not shown yet after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the current page is the Wrong password page for the public conversation link I wrote down
	 */
	public function iSeeThatTheCurrentPageIsTheWrongPasswordPageForThePublicConversationLinkIWroteDown() {
		// The authenticate page for shared links in Files app has a special
		// URL, but the authenticate page for public conversations does not, so
		// it needs to be checked that the warning is shown instead.
		if (!WaitFor::elementToBeEventuallyShown(
			$this->actor,
			self::wrongPasswordMessage(),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The wrong password warning was not shown yet after $timeout seconds");
		}
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

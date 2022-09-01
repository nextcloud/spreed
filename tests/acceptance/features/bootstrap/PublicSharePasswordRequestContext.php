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

class PublicSharePasswordRequestContext implements Context, ActorAwareInterface {
	use ActorAware;
	use ChatAncestorSetter;

	/**
	 * @return Locator
	 */
	public static function requestPasswordButton() {
		return Locator::forThe()->button("Request password")->
				describedAs("Request password button in the public share authentication page");
	}

	/**
	 * @return Locator
	 */
	public static function talkSidebar() {
		return Locator::forThe()->css("#talk-sidebar")->
				describedAs("Talk sidebar in the public share authentication page");
	}

	/**
	 * @When I request the password
	 */
	public function iRequestThePassword() {
		$this->actor->find(self::requestPasswordButton(), 10)->click();

		$this->setChatAncestorForActor(self::talkSidebar(), $this->actor);
	}

	/**
	 * @Then I see that the request password button is shown
	 */
	public function iSeeThatTheRequestPasswordButtonIsShown() {
		if (!WaitFor::elementToBeEventuallyShown(
			$this->actor,
			self::requestPasswordButton(),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The request password button is not shown yet after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the request password button is not shown
	 */
	public function iSeeThatTheRequestPasswordButtonIsNotShown() {
		try {
			// Wait a little before deciding that the button is not shown, as
			// the button would be loaded after the page has loaded.
			PHPUnit_Framework_Assert::assertFalse(
				$this->actor->find(self::requestPasswordButton(), 5)->isVisible());
		} catch (NoSuchElementException $exception) {
		}
	}
}

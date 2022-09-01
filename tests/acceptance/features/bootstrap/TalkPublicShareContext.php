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

class TalkPublicShareContext extends PublicShareContext {
	use ChatAncestorSetter;

	/**
	 * @return Locator
	 */
	public static function talkSidebar() {
		return Locator::forThe()->css("#talk-sidebar")->
				describedAs("Talk sidebar in the public share page");
	}

	/**
	 * @override I see that the current page is the shared link I wrote down
	 */
	public function iSeeThatTheCurrentPageIsTheSharedLinkIWroteDown() {
		parent::iSeeThatTheCurrentPageIsTheSharedLinkIWroteDown();

		$this->setChatAncestorForActor(self::talkSidebar(), $this->actor);
	}

	/**
	 * @Then I see that the Talk sidebar is shown in the public share page
	 */
	public function iSeeThatTheTalkSidebarIsShownInThePublicSharePage() {
		if (!WaitFor::elementToBeEventuallyShown(
			$this->actor,
			self::talkSidebar(),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The Talk sidebar is not shown yet after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the Talk sidebar is not shown in the public share page
	 */
	public function iSeeThatTheTalkSidebarIsNotShownInThePublicSharePage() {
		try {
			// Wait a little before deciding that the sidebar is not shown, as
			// the sidebar would be loaded after the page has loaded.
			$this->actor->find(self::talkSidebar(), 5);

			// Once the sidebar has loaded it is not immediately shown; it will
			// be shown once it has joined the room, so wait a little more to
			// "ensure" that it is not shown.
			sleep(2 * $this->actor->getFindTimeoutMultiplier());

			PHPUnit_Framework_Assert::assertFalse(
				$this->actor->find(self::talkSidebar())->isVisible());
		} catch (NoSuchElementException $exception) {
		}
	}
}

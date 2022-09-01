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

class ConversationListContext implements Context, ActorAwareInterface {
	use ActorAware;
	use ChatAncestorSetter;

	/**
	 * @return Locator
	 */
	public static function appNavigation() {
		return Locator::forThe()->id("app-navigation")->
				describedAs("App navigation");
	}

	/**
	 * @return Locator
	 */
	public static function showCreateConversationDropdownButton() {
		return Locator::forThe()->css("#oca-spreedme-add-room .select2-choice")->
				descendantOf(self::appNavigation())->
				describedAs("Show create conversation dropdown button in App navigation");
	}

	/**
	 * @return Locator
	 */
	public static function conversationList() {
		return Locator::forThe()->id("spreedme-room-list")->
				descendantOf(self::appNavigation())->
				describedAs("Conversation list in App navigation");
	}

	/**
	 * @return Locator
	 */
	public static function conversationListItemFor($conversation) {
		return Locator::forThe()->xpath("//a[normalize-space() = '$conversation']/ancestor::li")->
				descendantOf(self::conversationList())->
				describedAs("$conversation item in conversation list in App navigation");
	}

	/**
	 * @return Locator
	 */
	public static function activeConversationListItemFor($conversation) {
		return Locator::forThe()->xpath("//a[normalize-space() = '$conversation']/ancestor::li[contains(concat(' ', normalize-space(@class), ' '), ' active ')]")->
				descendantOf(self::conversationList())->
				describedAs("$conversation item in conversation list in App navigation");
	}

	/**
	 * @return Locator
	 */
	public static function conversationMenuButtonFor($conversation) {
		return Locator::forThe()->css(".app-navigation-entry-utils-menu-button button")->
				descendantOf(self::conversationListItemFor($conversation))->
				describedAs("Menu button for $conversation in conversation list in App navigation");
	}

	/**
	 * @return Locator
	 */
	public static function conversationMenuFor($conversation) {
		return Locator::forThe()->css(".app-navigation-entry-menu")->
				descendantOf(self::conversationListItemFor($conversation))->
				describedAs("Menu for $conversation in conversation list in App navigation");
	}

	/**
	 * @return Locator
	 */
	private static function conversationMenuItemFor($conversation, $item) {
		return Locator::forThe()->xpath("//button[normalize-space() = '$item']")->
				descendantOf(self::conversationMenuFor($conversation))->
				describedAs("$item item in menu for $conversation in conversation list in App navigation");
	}

	/**
	 * @return Locator
	 */
	public static function leaveConversationMenuItemFor($conversation) {
		return self::conversationMenuItemFor($conversation, "Leave conversation");
	}

	/**
	 * @return Locator
	 */
	public static function deleteConversationMenuItemFor($conversation) {
		return self::conversationMenuItemFor($conversation, "Delete conversation");
	}

	/**
	 * @Given I create a group conversation named :name
	 */
	public function iCreateAGroupConversationNamed($name) {
		// When the Talk app is opened and there are no conversations the
		// dropdown is automatically shown, and when the dropdown is shown
		// clicking on the button to open it fails because it is covered by the
		// search field of the dropdown. Due to that first it is assumed that
		// the dropdown is shown and the item is searched and directly clicked;
		// if it was not shown, then it is explicitly shown and after that the
		// item is searched and clicked.
		try {
			$this->setValueForSearchInputInSelect2Dropdown($name);
			$this->actor->find(TalkAppContext::itemInSelect2DropdownFor($name), 2)->click();
		} catch (NoSuchElementException $exception) {
			$this->actor->find(self::showCreateConversationDropdownButton(), 10)->click();
			$this->setValueForSearchInputInSelect2Dropdown($name);
			$this->actor->find(TalkAppContext::itemInSelect2DropdownFor($name), 2)->click();
		}

		$this->setChatAncestorForActor(TalkAppContext::mainView(), $this->actor);
	}

	/**
	 * @Given I create a public conversation named :name
	 */
	public function iCreateAPublicConversationNamed($name) {
		// When the Talk app is opened and there are no conversations the
		// dropdown is automatically shown, and when the dropdown is shown
		// clicking on the button to open it fails because it is covered by the
		// search field of the dropdown. Due to that first it is assumed that
		// the dropdown is shown and the item is searched and directly clicked;
		// if it was not shown, then it is explicitly shown and after that the
		// item is searched and clicked.
		try {
			$this->setValueForSearchInputInSelect2Dropdown($name);
			$this->actor->find(TalkAppContext::itemInSelect2DropdownFor($name . " (public)"), 2)->click();
		} catch (NoSuchElementException $exception) {
			$this->actor->find(self::showCreateConversationDropdownButton(), 10)->click();
			$this->setValueForSearchInputInSelect2Dropdown($name);
			$this->actor->find(TalkAppContext::itemInSelect2DropdownFor($name . " (public)"), 2)->click();
		}

		$this->setChatAncestorForActor(TalkAppContext::mainView(), $this->actor);
	}

	private function setValueForSearchInputInSelect2Dropdown($value) {
		// When "setValue" is used on an element, the Selenium2 driver for Mink
		// used in the acceptance tests does not send only the given value; it
		// prepends as many backspace and delete keys as needed to remove the
		// current value and appends a tab key to leave/unfocus the field and
		// ensure that the browsers triggers the change event. However, when the
		// search input of select2 is left the dropdown is closed, which
		// prevents choosing the filtered options. Due to this it is necessary
		// to directly post the desired value to the WebDriverElement instead of
		// through the MinkElement.
		$minkElement = $this->actor->find(TalkAppContext::searchInputInSelect2Dropdown(), 2)->getWrappedElement();
		$selenium2Driver = $this->actor->getSession()->getDriver();
		$webDriverSession = $selenium2Driver->getWebDriverSession();
		$webDriverElement = $webDriverSession->element('xpath', $minkElement->getXpath());
		// It is assumed that the search input is empty, so no backspace or
		// delete keys are sent to remove the previous content like done in the
		// Selenium2 driver for Mink.
		$webDriverElement->postValue(['value' => [$value]]);
	}

	/**
	 * @Given I create a one-to-one conversation with :userName
	 */
	public function iCreateAOneToOneConversationWith($userName) {
		// When the Talk app is opened and there are no conversations the
		// dropdown is automatically shown, and when the dropdown is shown
		// clicking on the button to open it fails because it is covered by the
		// search field of the dropdown. Due to that first it is assumed that
		// the dropdown is shown and the item is directly clicked; if it was not
		// shown, then it is explicitly shown and after that the item is
		// clicked.
		try {
			$this->actor->find(TalkAppContext::itemInSelect2DropdownFor($userName), 2)->click();
		} catch (NoSuchElementException $exception) {
			$this->actor->find(self::showCreateConversationDropdownButton(), 10)->click();
			$this->actor->find(TalkAppContext::itemInSelect2DropdownFor($userName), 2)->click();
		}

		$this->setChatAncestorForActor(TalkAppContext::mainView(), $this->actor);
	}

	/**
	 * @Given I open the :conversation conversation
	 */
	public function iOpenTheConversation($conversation) {
		$this->actor->find(self::conversationListItemFor($conversation), 10)->click();
	}

	/**
	 * @Given I leave the :conversation conversation
	 */
	public function iRemoveTheConversationFromTheList($conversation) {
		$this->actor->find(self::conversationMenuButtonFor($conversation), 10)->click();
		$this->actor->find(self::leaveConversationMenuItemFor($conversation), 2)->click();
	}

	/**
	 * @Given I delete the :conversation conversation
	 */
	public function iDeleteTheConversation($conversation) {
		$this->actor->find(self::conversationMenuButtonFor($conversation), 10)->click();
		$this->actor->find(self::deleteConversationMenuItemFor($conversation), 2)->click();
	}

	/**
	 * @Then I see that the :conversation conversation is shown in the list
	 */
	public function iSeeThatTheConversationIsShownInTheList($conversation) {
		if (!WaitFor::elementToBeEventuallyShown(
			$this->actor,
			self::conversationListItemFor($conversation),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The $conversation conversation is not shown yet in the list after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the :conversation conversation is not shown in the list
	 */
	public function iSeeThatTheConversationIsNotShownInTheList($conversation) {
		if (!WaitFor::elementToBeEventuallyNotShown(
			$this->actor,
			self::conversationListItemFor($conversation),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The $conversation conversation is still shown in the list after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the :conversation conversation is active
	 */
	public function iSeeThatTheConversationIsActive($conversation) {
		// The active conversation list item may be hidden but exist in the DOM
		// during the lapse between removing the conversation and getting the
		// updated conversation list from the server, so it has to be explictly
		// waited for it to be visible instead of relying on the implicit wait
		// made to find the element.
		if (!WaitFor::elementToBeEventuallyShown(
			$this->actor,
			self::activeConversationListItemFor($conversation),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The $conversation conversation is not active yet after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the :conversation conversation is not active
	 */
	public function iSeeThatTheConversationIsNotActive($conversation) {
		if (!WaitFor::elementToBeEventuallyNotShown(
			$this->actor,
			self::activeConversationListItemFor($conversation),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The $conversation conversation is still active after $timeout seconds");
		}
	}
}

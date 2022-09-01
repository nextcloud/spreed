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

class ConversationInfoContext implements Context, ActorAwareInterface {
	use ActorAware;

	/**
	 * @return Locator
	 */
	public static function conversationInfoContainer() {
		return Locator::forThe()->css(".detailCallInfoContainer")->
				descendantOf(TalkAppContext::sidebar())->
				describedAs("Conversation info container in the sidebar");
	}

	/**
	 * @return Locator
	 */
	public static function conversationNameEditableTextLabel() {
		return Locator::forThe()->css(".room-name")->
				descendantOf(self::conversationInfoContainer())->
				describedAs("Conversation name editable text label in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function conversationNameLabel() {
		return Locator::forThe()->css(".label")->
				descendantOf(self::conversationNameEditableTextLabel())->
				describedAs("Conversation name label in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function editConversationNameButton() {
		return Locator::forThe()->css(".edit-button")->
				descendantOf(self::conversationNameEditableTextLabel())->
				describedAs("Edit conversation name button in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function conversationNameTextInput() {
		return Locator::forThe()->xpath("//input[@type = 'text']")->
				descendantOf(self::conversationNameEditableTextLabel())->
				describedAs("Conversation name text input in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function copyLinkButton() {
		return Locator::forThe()->css(".clipboard-button")->
				descendantOf(self::conversationInfoContainer())->
				describedAs("Copy link button in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function roomModerationButton() {
		return Locator::forThe()->css(".room-moderation-button")->
				descendantOf(self::conversationInfoContainer())->
				describedAs("Room moderation button in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function roomModerationMenu() {
		return Locator::forThe()->css(".menu")->
				descendantOf(self::roomModerationButton())->
				describedAs("Room moderation menu in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function passwordIcon() {
		return Locator::forThe()->css(".icon-password")->
				descendantOf(self::roomModerationMenu())->
				describedAs("Password icon in room moderation menu in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function noPasswordIcon() {
		return Locator::forThe()->css(".icon-no-password")->
				descendantOf(self::roomModerationMenu())->
				describedAs("No password icon in room moderation menu in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function passwordField() {
		return Locator::forThe()->css(".password-input")->
				descendantOf(self::roomModerationMenu())->
				describedAs("Password field in room moderation menu in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function enableLobbyCheckbox() {
		// forThe()->checkbox("Enable lobby") can not be used here; that would
		// return the checkbox itself, but the element that the user interacts
		// with is the label.
		return Locator::forThe()->css(".lobby-checkbox-label")->
				descendantOf(self::roomModerationMenu())->
				describedAs("Enable lobby checkbox in room moderation menu in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function enableLobbyCheckboxInput() {
		return Locator::forThe()->checkbox("Enable lobby")->
				descendantOf(self::roomModerationMenu())->
				describedAs("Enable lobby checkbox input in room moderation menu in conversation info");
	}

	/**
	 * @Given I rename the conversation to :newConversationName
	 */
	public function iRenameTheConversationTo($newConversationName) {
		$this->actor->find(self::conversationNameLabel(), 10)->click();
		$this->actor->find(self::editConversationNameButton(), 2)->click();
		$this->actor->find(self::conversationNameTextInput(), 2)->setValue($newConversationName . "\r");
	}

	/**
	 * @Given I write down the public conversation link
	 */
	public function iWriteDownThePublicConversationLink() {
		$this->actor->find(self::copyLinkButton(), 10)->click();

		// Clicking on the menu item copies the link to the clipboard, but it is
		// not possible to access that value from the acceptance tests. Due to
		// this the value of the attribute that holds the URL is used instead.
		$this->actor->getSharedNotebook()["public conversation link"] = $this->actor->find(self::copyLinkButton(), 2)->getWrappedElement()->getAttribute("data-clipboard-text");
	}

	/**
	 * @When I protect the conversation with the password :password
	 */
	public function iProtectTheConversationWithThePassword($password) {
		$this->showRoomModerationMenu();

		$this->actor->find(self::passwordField(), 2)->setValue($password . "\r");
	}

	/**
	 * @When I enable the conversation lobby
	 */
	public function iEnableTheConversationLobby() {
		$this->iSeeThatTheConversationLobbyIsNotEnabled();

		$this->showRoomModerationMenu();

		$this->actor->find(self::enableLobbyCheckbox(), 2)->click();
	}

	/**
	 * @When I disable the conversation lobby
	 */
	public function iDisableTheConversationLobby() {
		$this->iSeeThatTheConversationLobbyIsEnabled();

		$this->showRoomModerationMenu();

		$this->actor->find(self::enableLobbyCheckbox(), 2)->click();
	}

	/**
	 * @Then I see that the conversation is password protected
	 */
	public function iSeeThatTheConversationIsPasswordProtected() {
		$this->showRoomModerationMenu();

		PHPUnit_Framework_Assert::assertTrue($this->actor->find(self::passwordIcon(), 10)->isVisible(), "Password icon is visible");

		// Hide menu again after checking the icon.
		$this->actor->find(self::roomModerationButton(), 2)->click();
	}

	/**
	 * @Then I see that the conversation is not password protected
	 */
	public function iSeeThatTheConversationIsNotPasswordProtected() {
		$this->showRoomModerationMenu();

		PHPUnit_Framework_Assert::assertTrue($this->actor->find(self::noPasswordIcon(), 10)->isVisible(), "No password icon is visible");

		// Hide menu again after checking the icon.
		$this->actor->find(self::roomModerationButton(), 2)->click();
	}

	/**
	 * @Then I see that the conversation lobby is enabled
	 */
	public function iSeeThatTheConversationLobbyIsEnabled() {
		$this->showRoomModerationMenu();

		PHPUnit_Framework_Assert::assertTrue($this->actor->find(self::enableLobbyCheckboxInput(), 10)->isChecked(), "Enable lobby checkbox is checked");

		// Hide menu again after checking the button.
		$this->actor->find(self::roomModerationButton(), 2)->click();
	}

	/**
	 * @Then I see that the conversation lobby is not enabled
	 */
	public function iSeeThatTheConversationLobbyIsNotEnabled() {
		$this->showRoomModerationMenu();

		PHPUnit_Framework_Assert::assertFalse($this->actor->find(self::enableLobbyCheckboxInput(), 10)->isChecked(), "Enable lobby checkbox is not checked");

		// Hide menu again after checking the button.
		$this->actor->find(self::roomModerationButton(), 2)->click();
	}

	private function showRoomModerationMenu() {
		// The room moderation menu is hidden after clicking on an action of the
		// menu. Therefore, if the menu is visible, wait a little just in case
		// it is in the process of being hidden due to a previous action.
		if (!WaitFor::elementToBeEventuallyNotShown(
			$this->actor,
			self::roomModerationMenu(),
			$timeout = 5 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The room moderation menu is still shown after $timeout seconds");
		}

		$this->actor->find(self::roomModerationButton(), 10)->click();
	}
}

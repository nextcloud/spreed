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
	public static function passwordButton() {
		return Locator::forThe()->css(".password-button")->
				descendantOf(self::conversationInfoContainer())->
				describedAs("Password button in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function passwordIcon() {
		return Locator::forThe()->css(".icon-password")->
				descendantOf(self::passwordButton())->
				describedAs("Password icon in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function noPasswordIcon() {
		return Locator::forThe()->css(".icon-no-password")->
				descendantOf(self::passwordButton())->
				describedAs("No password icon in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function passwordField() {
		return Locator::forThe()->css(".password-input")->
				descendantOf(self::conversationInfoContainer())->
				describedAs("Password field in conversation info");
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
		$this->actor->find(self::passwordButton(), 2)->click();

		$this->actor->find(self::passwordField(), 2)->setValue($password . "\r");
	}

	/**
	 * @Then I see that the conversation is password protected
	 */
	public function iSeeThatTheConversationIsPasswordProtected() {
		PHPUnit_Framework_Assert::assertTrue($this->actor->find(self::passwordIcon(), 10)->isVisible(), "Password icon is visible");
	}

	/**
	 * @Then I see that the conversation is not password protected
	 */
	public function iSeeThatTheConversationIsNotPasswordProtected() {
		PHPUnit_Framework_Assert::assertTrue($this->actor->find(self::noPasswordIcon(), 10)->isVisible(), "No password icon is visible");
	}

}

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
	use ChatAncestorSetter;

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
	public static function joinCallButton() {
		return Locator::forThe()->css(".join-call")->
				descendantOf(self::conversationInfoContainer())->
				describedAs("Join call button in conversation info");
	}

	/**
	 * @return Locator
	 */
	public static function leaveCallButton() {
		return Locator::forThe()->css(".leave-call")->
				descendantOf(self::conversationInfoContainer())->
				describedAs("Leave call button in conversation info");
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
	 * @Given I join the call
	 */
	public function iJoinTheCall() {
		$this->actor->find(self::joinCallButton(), 10)->click();

		$this->setChatAncestorForActor(TalkAppContext::sidebar(), $this->actor);
	}

	/**
	 * @Given I leave the call
	 */
	public function iLeaveTheCall() {
		$this->actor->find(self::leaveCallButton(), 10)->click();

		$this->setChatAncestorForActor(TalkAppContext::mainView(), $this->actor);
	}

}

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

class ChatContext implements Context, ActorAwareInterface {
	/**
	 * @var Actor
	 */
	private $actor;

	/**
	 * @var array
	 */
	private $chatAncestorsByActor;

	/**
	 * @var Locator
	 */
	private $chatAncestor;

	/**
	 * @BeforeScenario
	 */
	public function initializeChatAncestors() {
		$this->chatAncestorsByActor = [];
		$this->chatAncestor = null;
	}

	/**
	 * @param Actor $actor
	 */
	public function setCurrentActor(Actor $actor) {
		$this->actor = $actor;

		if (array_key_exists($actor->getName(), $this->chatAncestorsByActor)) {
			$this->chatAncestor = $this->chatAncestorsByActor[$actor->getName()];
		} else {
			$this->chatAncestor = null;
		}
	}

	/**
	 * Sets the chat ancestor to be used in the steps performed by the given
	 * actor from that point on (until changed again).
	 *
	 * This is meant to be called from other contexts, for example, when the
	 * user joins or leaves a video call.
	 *
	 * The ChatAncestorSetter trait can be used to reduce the boilerplate needed
	 * to set the chat ancestor from other contexts.
	 *
	 * @param null|Locator $chatAncestor the chat ancestor
	 * @param Actor $actor the actor
	 */
	public function setChatAncestorForActor($chatAncestor, Actor $actor) {
		$this->chatAncestorsByActor[$actor->getName()] = $chatAncestor;
	}

	/**
	 * @return Locator
	 */
	public static function chatView($chatAncestor) {
		return Locator::forThe()->css(".chat")->
				descendantOf($chatAncestor)->
				describedAs("Chat view in Talk app");
	}

	/**
	 * @return Locator
	 */
	public static function chatMessagesList($chatAncestor) {
		return Locator::forThe()->css(".comments")->
				descendantOf(self::chatView($chatAncestor))->
				describedAs("List of received chat messages");
	}

	/**
	 * @return Locator
	 */
	public static function chatMessagesWrapper($chatAncestor) {
		return Locator::forThe()->css(".wrapper")->
				descendantOf(self::chatMessagesList($chatAncestor))->
				describedAs("Wrapper for visible messages in the list of received chat messages");
	}

	/**
	 * @return Locator
	 */
	public static function chatMessage($chatAncestor, $number) {
		return Locator::forThe()->xpath("li[not(contains(concat(' ', normalize-space(@class), ' '), ' systemMessage '))][$number]")->
				descendantOf(self::chatMessagesWrapper($chatAncestor))->
				describedAs("Chat message $number in the list of received messages");
	}

	/**
	 * @return Locator
	 */
	public static function groupedChatMessage($chatAncestor, $number) {
		return Locator::forThe()->xpath("li[not(contains(concat(' ', normalize-space(@class), ' '), ' systemMessage '))][position() = $number and contains(concat(' ', normalize-space(@class), ' '), ' grouped ')]")->
				descendantOf(self::chatMessagesWrapper($chatAncestor))->
				describedAs("Grouped chat message $number in the list of received messages");
	}

	/**
	 * @return Locator
	 */
	public static function authorOfChatMessage($chatAncestor, $number) {
		return Locator::forThe()->css(".author")->
				descendantOf(self::chatMessage($chatAncestor, $number))->
				describedAs("Author of chat message $number in the list of received messages");
	}

	/**
	 * @return Locator
	 */
	public static function textOfChatMessage($chatAncestor, $number) {
		return Locator::forThe()->css(".message")->
				descendantOf(self::chatMessage($chatAncestor, $number))->
				describedAs("Text of chat message $number in the list of received messages");
	}

	/**
	 * @return Locator
	 */
	public static function textOfGroupedChatMessage($chatAncestor, $number) {
		return Locator::forThe()->css(".message")->
				descendantOf(self::groupedChatMessage($chatAncestor, $number))->
				describedAs("Text of grouped chat message $number in the list of received messages");
	}

	/**
	 * @return Locator
	 */
	public static function formattedMentionInChatMessageOf($chatAncestor, $number, $user) {
		// User mentions have an image avatar, but guest mentions have a plain
		// text avatar, so the avatar needs to be excluded when matching the
		// name.
		return Locator::forThe()->xpath("span/span[contains(concat(' ', normalize-space(@class), ' '), ' mention-user ')]//*[normalize-space() = '$user']/ancestor::span")->
				descendantOf(self::textOfChatMessage($chatAncestor, $number))->
				describedAs("Formatted mention of $user in chat message $number in the list of received messages");
	}

	/**
	 * @return Locator
	 */
	public static function formattedMentionInChatMessageOfAsCurrentUser($chatAncestor, $number, $user) {
		// User mentions have an image avatar, but guest mentions have a plain
		// text avatar, so the avatar needs to be excluded when matching the
		// name.
		return Locator::forThe()->xpath("span/span[contains(concat(' ', normalize-space(@class), ' '), ' mention-user ') and contains(concat(' ', normalize-space(@class), ' '), ' currentUser ')]//*[normalize-space() = '$user']/ancestor::span")->
				descendantOf(self::textOfChatMessage($chatAncestor, $number))->
				describedAs("Formatted mention of $user as current user in chat message $number in the list of received messages");
	}

	/**
	 * @return Locator
	 */
	public static function formattedMentionInChatMessageOfAllParticipantsOf($chatAncestor, $number, $roomName) {
		return Locator::forThe()->xpath("span/span[contains(concat(' ', normalize-space(@class), ' '), ' mention-call ') and contains(concat(' ', normalize-space(@class), ' '), ' currentUser ') and normalize-space() = '$roomName']")->
				descendantOf(self::textOfChatMessage($chatAncestor, $number))->
				describedAs("Formatted mention of all participants of $roomName in chat message $number in the list of received messages");
	}

	/**
	 * @return Locator
	 */
	public static function formattedLinkInChatMessageTo($chatAncestor, $number, $url) {
		return Locator::forThe()->xpath("a[normalize-space(@href) = '$url']")->
				descendantOf(self::textOfChatMessage($chatAncestor, $number))->
				describedAs("Formatted link to $url in chat message $number in the list of received messages");
	}

	/**
	 * @return Locator
	 */
	public static function formattedFilePreviewInChatMessage($chatAncestor, $number) {
		return Locator::forThe()->css(".filePreviewContainer")->
				descendantOf(self::textOfChatMessage($chatAncestor, $number))->
				describedAs("Formatted file preview in chat message $number in the list of received messages");
	}

	/**
	 * @return Locator
	 */
	public static function newChatMessageRow($chatAncestor) {
		return Locator::forThe()->css(".newCommentRow")->
				descendantOf(self::chatView($chatAncestor))->
				describedAs("New chat message row");
	}

	/**
	 * @return Locator
	 */
	public static function userNameLabel($chatAncestor) {
		return Locator::forThe()->css(".author")->
				descendantOf(self::newChatMessageRow($chatAncestor))->
				describedAs("User name label");
	}

	/**
	 * @return Locator
	 */
	public static function guestNameEditableTextLabel($chatAncestor) {
		return Locator::forThe()->css(".guest-name.editable-text-label")->
				descendantOf(self::newChatMessageRow($chatAncestor))->
				describedAs("Guest name editable text label");
	}

	/**
	 * @return Locator
	 */
	public static function guestNameEditButton($chatAncestor) {
		return Locator::forThe()->css(".edit-button")->
				descendantOf(self::guestNameEditableTextLabel($chatAncestor))->
				describedAs("Guest name edit button");
	}

	/**
	 * @return Locator
	 */
	public static function guestNameInput($chatAncestor) {
		return Locator::forThe()->css(".username")->
				descendantOf(self::guestNameEditableTextLabel($chatAncestor))->
				describedAs("Guest name input");
	}

	/**
	 * @return Locator
	 */
	public static function newChatMessageForm($chatAncestor) {
		return Locator::forThe()->css(".newCommentForm")->
				descendantOf(self::newChatMessageRow($chatAncestor))->
				describedAs("New chat message form");
	}

	/**
	 * @return Locator
	 */
	public static function newChatMessageInput($chatAncestor) {
		return Locator::forThe()->css(".message")->
				descendantOf(self::newChatMessageForm($chatAncestor))->
				describedAs("New chat message input");
	}

	/**
	 * @return Locator
	 */
	public static function newChatMessageSendIcon($chatAncestor) {
		return Locator::forThe()->css(".icon-confirm")->
				descendantOf(self::newChatMessageForm($chatAncestor))->
				describedAs("New chat message send icon");
	}

	/**
	 * @return Locator
	 */
	public static function newChatMessageWorkingIcon($chatAncestor) {
		return Locator::forThe()->css(".submitLoading")->
				descendantOf(self::newChatMessageForm($chatAncestor))->
				describedAs("New chat message working icon");
	}

	/**
	 * @return Locator
	 */
	public static function shareButton($chatAncestor) {
		return Locator::forThe()->css(".share")->
				descendantOf(self::newChatMessageForm($chatAncestor))->
				describedAs("Share button");
	}

	/**
	 * @return Locator
	 */
	public static function mentionAutocompleteContainer() {
		// The container is added directly in the body, not in the chat view.
		// Moreover, there could be several atwho containers, so it needs to be
		// got based on the elements that it contains.
		return Locator::forThe()->xpath("//li[contains(concat(' ', normalize-space(@class), ' '), ' chat-view-mention-autocomplete ')]/ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' atwho-container ')]")->
				describedAs("Mention autocomplete container");
	}

	/**
	 * @return Locator
	 */
	public static function mentionAutocompleteCandidateFor($name) {
		// User mentions have an image avatar, but guest mentions have a plain
		// text avatar, so the avatar needs to be excluded when matching the
		// name.
		return Locator::forThe()->xpath("//li[contains(concat(' ', normalize-space(@class), ' '), ' chat-view-mention-autocomplete ')]//*[normalize-space() = '$name']/ancestor::li")->
				descendantOf(self::mentionAutocompleteContainer())->
				describedAs("Mention autocomplete candidate for $name");
	}

	/**
	 * @When I set my guest name to :name
	 */
	public function iSetMyGuestNameTo($name) {
		$this->actor->find(self::guestNameEditButton($this->chatAncestor), 10)->click();
		$this->actor->find(self::guestNameInput($this->chatAncestor), 2)->setValue($name . "\r");
	}

	/**
	 * @When I type a new chat message with the text :message
	 */
	public function iTypeANewChatMessageWithTheText($message) {
		// Instead of waiting for the input to be enabled before sending a new
		// message it is easier to wait for the working icon to not be shown.
		if (!WaitFor::elementToBeEventuallyNotShown(
			$this->actor,
			self::newChatMessageWorkingIcon($this->chatAncestor),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The working icon for the new message was still being shown after $timeout seconds");
		}

		$this->actor->find(self::newChatMessageInput($this->chatAncestor), 10)->setValue($message);
	}

	/**
	 * @When I choose the candidate mention for :name
	 */
	public function iChooseTheCandidateMentionFor($name) {
		$this->actor->find(self::mentionAutocompleteCandidateFor($name), 10)->click();
	}

	/**
	 * @When I send the current chat message
	 */
	public function iSendTheCurrentChatMessage() {
		$this->actor->find(self::newChatMessageSendIcon($this->chatAncestor), 10)->click();
	}

	/**
	 * @When I send a new chat message with the text :message
	 */
	public function iSendANewChatMessageWith($message) {
		// Instead of waiting for the input to be enabled before sending a new
		// message it is easier to wait for the working icon to not be shown.
		if (!WaitFor::elementToBeEventuallyNotShown(
			$this->actor,
			self::newChatMessageWorkingIcon($this->chatAncestor),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The working icon for the new message was still being shown after $timeout seconds");
		}

		$this->actor->find(self::newChatMessageInput($this->chatAncestor), 10)->setValue($message . "\r");
	}

	/**
	 * @When I start the share operation
	 */
	public function iStartTheShareOperation() {
		$this->actor->find(self::shareButton($this->chatAncestor), 10)->click();
	}

	/**
	 * @Then I see that the chat is shown in the main view
	 */
	public function iSeeThatTheChatIsShownInTheMainView() {
		PHPUnit_Framework_Assert::assertTrue($this->actor->find(self::chatView(TalkAppContext::mainView()), 10)->isVisible());
	}

	/**
	 * @Then I see that the current participant is the user :user
	 */
	public function iSeeThatTheCurrentParticipantIsTheUser($user) {
		PHPUnit_Framework_Assert::assertEquals($user, $this->actor->find(self::userNameLabel($this->chatAncestor), 10)->getText());
	}

	/**
	 * @Then I see that the message :number was sent by :author with the text :message
	 */
	public function iSeeThatTheMessageWasSentByWithTheText($number, $author, $message) {
		if (!WaitFor::elementToBeEventuallyShown(
			$this->actor,
			self::authorOfChatMessage($this->chatAncestor, $number),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The author of the message $number was not shown yet after $timeout seconds");
		}
		PHPUnit_Framework_Assert::assertEquals($author, $this->actor->find(self::authorOfChatMessage($this->chatAncestor, $number))->getText());

		if (!WaitFor::elementToBeEventuallyShown(
			$this->actor,
			self::textOfChatMessage($this->chatAncestor, $number),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The text of the message $number was not shown yet after $timeout seconds");
		}
		PHPUnit_Framework_Assert::assertEquals($message, $this->actor->find(self::textOfChatMessage($this->chatAncestor, $number))->getText());
	}

	/**
	 * @Then I see that the message :number was sent with the text :message and grouped with the previous one
	 */
	public function iSeeThatTheMessageWasSentWithTheTextAndGroupedWithThePreviousOne($number, $message) {
		if (!WaitFor::elementToBeEventuallyShown(
			$this->actor,
			self::textOfGroupedChatMessage($this->chatAncestor, $number),
			$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The text of the message $number was not shown yet after $timeout seconds");
		}
		PHPUnit_Framework_Assert::assertEquals($message, $this->actor->find(self::textOfGroupedChatMessage($this->chatAncestor, $number))->getText());

		// Author element is not visible for the message, so its text is
		// returned as an empty string (even if the element has actual text).
		PHPUnit_Framework_Assert::assertEquals("", $this->actor->find(self::authorOfChatMessage($this->chatAncestor, $number))->getText());
	}

	/**
	 * @Then I see that the message :number contains a formatted mention of :user
	 */
	public function iSeeThatTheMessageContainsAFormattedMentionOf($number, $user) {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::formattedMentionInChatMessageOf($this->chatAncestor, $number, $user), 10));
	}

	/**
	 * @Then I see that the message :number contains a formatted mention of :user as current user
	 */
	public function iSeeThatTheMessageContainsAFormattedMentionOfAsCurrentUser($number, $user) {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::formattedMentionInChatMessageOfAsCurrentUser($this->chatAncestor, $number, $user), 10));
	}

	/**
	 * @Then I see that the message :number contains a formatted mention of all participants of :roomName
	 */
	public function iSeeThatTheMessageContainsAFormattedMentionOfAllParticipantsOf($number, $roomName) {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::formattedMentionInChatMessageOfAllParticipantsOf($this->chatAncestor, $number, $roomName), 10));
	}

	/**
	 * @Then I see that the message :number contains a formatted link to :user
	 */
	public function iSeeThatTheMessageContainsAFormattedLinkTo($number, $url) {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::formattedLinkInChatMessageTo($this->chatAncestor, $number, $url), 10));
	}

	/**
	 * @Then I see that the message :number contains a formatted file preview
	 */
	public function iSeeThatTheMessageContainsAFormattedFilePreview($number) {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::formattedFilePreviewInChatMessage($this->chatAncestor, $number), 10));
	}
}

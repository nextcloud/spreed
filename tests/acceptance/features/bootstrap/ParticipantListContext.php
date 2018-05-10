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

class ParticipantListContext implements Context, ActorAwareInterface {

	use ActorAware;

	/**
	 * @return Locator
	 */
	public static function participantsTabView() {
		return Locator::forThe()->id("participantsTabView")->
				describedAs("Participants tab in the sidebar");
	}

	/**
	 * @return Locator
	 */
	public static function showParticipantDropdownButton() {
		return Locator::forThe()->css(".oca-spreedme-add-person .select2-choice")->
				descendantOf(self::participantsTabView())->
				describedAs("Show participant dropdown button in the sidebar");
	}

	/**
	 * @return Locator
	 */
	public static function participantsList() {
		return Locator::forThe()->css(".participantWithList")->
				descendantOf(self::participantsTabView())->
				describedAs("Participants list in the sidebar");
	}

	/**
	 * @return Locator
	 */
	public static function itemInParticipantsListFor($participantName) {
		return Locator::forThe()->xpath("//a/text()[normalize-space() = '$participantName']/ancestor::li")->
				descendantOf(self::participantsList())->
				describedAs("Item for $participantName in the participants list");
	}

	/**
	 * @return Locator
	 */
	public static function moderatorIndicatorFor($participantName) {
		return Locator::forThe()->css(".participant-moderator-indicator")->
				descendantOf(self::itemInParticipantsListFor($participantName))->
				describedAs("Moderator indicator for $participantName in the participants list");
	}

	/**
	 * @return Locator
	 */
	public static function participantMenuButtonFor($participantName) {
		return Locator::forThe()->css(".participant-entry-utils-menu-button button")->
				descendantOf(self::itemInParticipantsListFor($participantName))->
				describedAs("Menu button for $participantName in the participants list");
	}

	/**
	 * @return Locator
	 */
	public static function participantMenuFor($participantName) {
		return Locator::forThe()->css(".menu")->
				descendantOf(self::itemInParticipantsListFor($participantName))->
				describedAs("Menu for $participantName in the participants list");
	}

	/**
	 * @return Locator
	 */
	private static function participantMenuItemFor($participantName, $item) {
		return Locator::forThe()->xpath("//button[normalize-space() = '$item']")->
				descendantOf(self::participantMenuFor($participantName))->
				describedAs("$item item in menu for $participantName in the participants list");
	}

	/**
	 * @return Locator
	 */
	public static function promoteToModeratorMenuItemFor($participantName) {
		return self::participantMenuItemFor($participantName, "Promote to moderator");
	}

	/**
	 * @return Locator
	 */
	public static function demoteFromModeratorMenuItemFor($participantName) {
		return self::participantMenuItemFor($participantName, "Demote from moderator");
	}

	/**
	 * @return Locator
	 */
	public static function removeParticipantMenuItemFor($participantName) {
		return self::participantMenuItemFor($participantName, "Remove participant");
	}

	/**
	 * @return array
	 */
	public function participantsListItems() {
		return $this->actor->find(self::participantsList(), 10)
					->getWrappedElement()->findAll('xpath', '/li');
	}

	/**
	 * @When I add :participantName to the participants
	 */
	public function iAddToTheParticipants($participantName) {
		$this->actor->find(self::showParticipantDropdownButton(), 10)->click();
		$this->actor->find(TalkAppContext::itemInSelect2DropdownFor($participantName), 2)->click();
	}

	/**
	 * @When I promote :participantName to moderator
	 */
	public function iPromoteToModerator($participantName) {
		$this->actor->find(self::participantMenuButtonFor($participantName), 10)->click();
		$this->actor->find(self::promoteToModeratorMenuItemFor($participantName), 2)->click();
	}

	/**
	 * @When I demote :participantName from moderator
	 */
	public function iDemoteFromModerator($participantName) {
		$this->actor->find(self::participantMenuButtonFor($participantName), 10)->click();
		$this->actor->find(self::demoteFromModeratorMenuItemFor($participantName), 2)->click();
	}

	/**
	 * @When I remove :participantName from the participants
	 */
	public function iRemoveFromTheParticipants($participantName) {
		$this->actor->find(self::participantMenuButtonFor($participantName), 10)->click();
		$this->actor->find(self::removeParticipantMenuItemFor($participantName), 2)->click();
	}

	/**
	 * @Then I see that I can add new participants
	 */
	public function iSeeThatICanAddNewParticipants() {
		if (!WaitFor::elementToBeEventuallyShown(
				$this->actor,
				self::showParticipantDropdownButton(),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("Button to add new participants is not visible yet after $timeout seconds");
		}
	}

	/**
	 * @Then I see that I can not add new participants
	 */
	public function iSeeThatICanNotAddNewParticipants() {
		if (!WaitFor::elementToBeEventuallyNotShown(
				$this->actor,
				self::showParticipantDropdownButton(),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("Button to add new participants is still visible after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the number of participants shown in the list is :numberOfParticipants
	 */
	public function iSeeThatTheNumberOfParticipantsShownInTheListIs($numberOfParticipants) {
		$numberOfParticipantsMatchCallback = function() use ($numberOfParticipants) {
			try {
				return count($this->participantsListItems()) === intval($numberOfParticipants);
			} catch (NoSuchElementException $exception) {
				return false;
			}
		};

		if (!Utils::waitFor(
				$numberOfParticipantsMatchCallback,
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier(),
				$timeoutStep = 1)) {
			PHPUnit_Framework_Assert::fail("The number of participants is still not $numberOfParticipants after $timeout seconds");
		}
	}

	/**
	 * @Then I see that :participantName is shown in the list of participants as a moderator
	 */
	public function iSeeThatIsShownInTheListOfParticipantsAsAModerator($participantName) {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::itemInParticipantsListFor($participantName), 10));
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::moderatorIndicatorFor($participantName), 10));
	}

	/**
	 * @Then I see that :participantName is shown in the list of participants as a normal participant
	 */
	public function iSeeThatIsShownInTheListOfParticipantsAsANormalParticipant($participantName) {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::itemInParticipantsListFor($participantName), 10));

		if (!WaitFor::elementToBeEventuallyNotShown(
				$this->actor,
				self::moderatorIndicatorFor($participantName),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("Participant $participantName is still marked as a moderator after $timeout seconds but it should be a normal participant instead");
		}
	}

	/**
	 * @Then I see that I can moderate :participantName
	 */
	public function iSeeThatICanModerate($participantName) {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::itemInParticipantsListFor($participantName), 10));

		if (!WaitFor::elementToBeEventuallyShown(
				$this->actor,
				self::participantMenuButtonFor($participantName),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("Participant $participantName can not be moderated yet after $timeout seconds");
		}
	}

	/**
	 * @Then I see that I can not moderate :participantName
	 */
	public function iSeeThatICanNotModerate($participantName) {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::itemInParticipantsListFor($participantName), 10));

		if (!WaitFor::elementToBeEventuallyNotShown(
				$this->actor,
				self::participantMenuButtonFor($participantName),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("Participant $participantName can still be moderated after $timeout seconds");
		}
	}

}

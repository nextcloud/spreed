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

class CallContext implements Context, ActorAwareInterface {

	use ActorAware;

	/**
	 * @return Locator
	 */
	public static function localContainer() {
		return Locator::forThe()->css("#localVideoContainer")->
				descendantOf(TalkAppContext::mainView())->
				describedAs("Local container of the call in the main view");
	}

	/**
	 * @return Locator
	 */
	public static function localVideo() {
		return Locator::forThe()->css("video")->
				descendantOf(self::localContainer())->
				describedAs("Video in the local container of the call in the main view");
	}

	/**
	 * @return Locator
	 */
	public static function localAvatar() {
		return Locator::forThe()->css(".avatar-container")->
				descendantOf(self::localContainer())->
				describedAs("Avatar in the local container of the call in the main view");
	}

	/**
	 * @return Locator
	 */
	public static function localAudioEnabledIndicator() {
		return Locator::forThe()->css(".icon-audio:not(.audio-disabled):not(.no-audio-available)")->
				descendantOf(self::localContainer())->
				describedAs("Audio enabled indicator in the local container of the call in the main view");
	}

	/**
	 * @return Locator
	 */
	public static function localAudioNotAvailableIndicator() {
		return Locator::forThe()->css(".no-audio-available")->
				descendantOf(self::localContainer())->
				describedAs("Audio not available indicator in the local container of the call in the main view");
	}

	/**
	 * @return Locator
	 */
	public static function localVideoEnabledIndicator() {
		return Locator::forThe()->css(".icon-video:not(.video-disabled):not(.no-video-available)")->
				descendantOf(self::localContainer())->
				describedAs("Video enabled indicator in the local container of the call in the main view");
	}

	/**
	 * @return Locator
	 */
	public static function localVideoNotAvailableIndicator() {
		return Locator::forThe()->css(".no-video-available")->
				descendantOf(self::localContainer())->
				describedAs("Video not available indicator in the local container of the call in the main view");
	}

	/**
	 * @return Locator
	 */
	public static function promotedContainer() {
		return Locator::forThe()->css(".videoContainer.promoted")->
				descendantOf(TalkAppContext::mainView())->
				describedAs("Promoted container of the call in the main view");
	}

	/**
	 * @return Locator
	 */
	public static function promotedDummyContainer() {
		// A dummy container is used to show the user name and the media
		// permissions at the same place shown in the unpromoted container for
		// the user (as the promoted container is centered and the unpromoted
		// container can be anywhere in the bottom area).
		return Locator::forThe()->css(".videoContainer-dummy")->
				descendantOf(TalkAppContext::mainView())->
				describedAs("Promoted dummy container of the call in the main view");
	}

	/**
	 * @return Locator
	 */
	public static function promotedVideo() {
		return Locator::forThe()->css("video")->
				descendantOf(self::promotedContainer())->
				describedAs("Video in the promoted container of the call in the main view");
	}

	/**
	 * @return Locator
	 */
	public static function promotedAvatar() {
		return Locator::forThe()->css(".avatar-container")->
				descendantOf(self::promotedContainer())->
				describedAs("Avatar in the promoted container of the call in the main view");
	}

	/**
	 * @return Locator
	 */
	public static function promotedUserName() {
		return Locator::forThe()->css(".nameIndicator")->
				descendantOf(self::promotedDummyContainer())->
				describedAs("Name indicator in the promoted dummy container of the call in the main view");
	}

	/**
	 * @return Locator
	 */
	public static function promotedAudioEnabledIndicator() {
		return Locator::forThe()->css(".audio-on")->
				descendantOf(self::promotedDummyContainer())->
				describedAs("Audio enabled indicator in the promoted dummy container of the call in the main view");
	}

	/**
	 * @return Locator
	 */
	public static function promotedVideoEnabledIndicator() {
		return Locator::forThe()->css(".icon-video")->
				descendantOf(self::promotedDummyContainer())->
				describedAs("Video enabled indicator in the promoted dummy container of the call in the main view");
	}

	/**
	 * @Then I see that the local audio is enabled
	 */
	public function iSeeThatTheLocalAudioIsEnabled() {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::localAudioEnabledIndicator(), 10));
	}

	/**
	 * @Then I see that the local audio is not available
	 */
	public function iSeeThatTheLocalAudioIsNotAvailable() {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::localAudioNotAvailableIndicator(), 10));
	}

	/**
	 * @Then I see that the local video is enabled
	 */
	public function iSeeThatTheLocalVideoIsEnabled() {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::localVideoEnabledIndicator(), 10));
	}

	/**
	 * @Then I see that the local video is not available
	 */
	public function iSeeThatTheLocalVideoIsNotAvailable() {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::localVideoNotAvailableIndicator(), 10));
	}

	/**
	 * @Then I see that the local video is shown
	 */
	public function iSeeThatTheLocalVideoIsShown() {
		if (!WaitFor::elementToBeEventuallyShown(
				$this->actor,
				self::localVideo(),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The local video was not shown yet after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the local video is not shown
	 */
	public function iSeeThatTheLocalVideoIsNotShown() {
		if (!WaitFor::elementToBeEventuallyNotShown(
				$this->actor,
				self::localVideo(),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The local video is still shown after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the local avatar is shown
	 */
	public function iSeeThatTheLocalAvatarIsShown() {
		if (!WaitFor::elementToBeEventuallyShown(
				$this->actor,
				self::localAvatar(),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The local avatar was not shown yet after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the local avatar is not shown
	 */
	public function iSeeThatTheLocalAvatarIsNotShown() {
		if (!WaitFor::elementToBeEventuallyNotShown(
				$this->actor,
				self::localAvatar(),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The local avatar is still shown after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the promoted video is shown
	 */
	public function iSeeThatThePromotedVideoIsShown() {
		if (!WaitFor::elementToBeEventuallyShown(
				$this->actor,
				self::promotedVideo(),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The promoted video was not shown yet after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the promoted video is not shown
	 */
	public function iSeeThatThePromotedVideoIsNotShown() {
		if (!WaitFor::elementToBeEventuallyNotShown(
				$this->actor,
				self::promotedVideo(),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The promoted video is still shown after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the promoted avatar is shown
	 */
	public function iSeeThatThePromotedAvatarIsShown() {
		if (!WaitFor::elementToBeEventuallyShown(
				$this->actor,
				self::promotedAvatar(),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The promoted avatar was not shown yet after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the promoted avatar is not shown
	 */
	public function iSeeThatThePromotedAvatarIsNotShown() {
		if (!WaitFor::elementToBeEventuallyNotShown(
				$this->actor,
				self::promotedAvatar(),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The promoted avatar is still shown after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the promoted user is :userName
	 */
	public function iSeeThatThePromotedUserIs($userName) {
		$promotedUserNameMatchedCallback = function() use($userName) {
			try {
				$foundUserName = $this->actor->find(self::promotedUserName())->getText();
			} catch (NoSuchElementException $exception) {
				return false;
			}

			if ($foundUserName == $userName) {
			    return true;
			}

			return false;
		};

		if (!Utils::waitFor($promotedUserNameMatchedCallback, $timeout = 10 * $this->actor->getFindTimeoutMultiplier(), $timeoutStep = 1)) {
			PHPUnit_Framework_Assert::fail("The promoted user name was not $userName yet after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the promoted audio is enabled
	 */
	public function iSeeThatThePromotedAudioIsEnabled() {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::promotedAudioEnabledIndicator(), 10));
	}

	/**
	 * @Then I see that the promoted video is enabled
	 */
	public function iSeeThatThePromotedVideoIsEnabled() {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::promotedVideoEnabledIndicator(), 10));
	}

}

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

class FilesAppRoomSharingContext implements Context, ActorAwareInterface {
	use ActorAware;

	/**
	 * @Then I see that the file is shared with me in the conversation :conversationName by :sharedByName
	 */
	public function iSeeThatTheFileIsSharedWithMeInTheConversationBy($conversationName, $sharedByName) {
		PHPUnit_Framework_Assert::assertEquals(
			$this->actor->find(FilesAppSharingContext::sharedByLabel(), 10)->getText(), "Shared with you and the conversation $conversationName by $sharedByName");
	}
}

import axios from 'nextcloud-axios'
import { generateOcsUrl } from 'nextcloud-router'

const fetchConversations = async function() {
	try {
		const response = await axios.get(generateOcsUrl('apps/spreed/api/v1', 2) + 'room')
		return response
	} catch (error) {
		console.debug(error)
	}
}

export { fetchConversations }

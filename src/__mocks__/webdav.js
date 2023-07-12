// eslint-disable-next-line n/no-unpublished-import
import { createClient } from 'webdav'

jest.mock('webdav', () => ({
	createClient: jest.fn(),
}))

export { createClient }
